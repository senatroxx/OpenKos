<?php

namespace App\Actions\Leases;

use App\Actions\Invoices\GenerateInvoices;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\CreateLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Services\Payments\MoneyConverter;
use App\Services\ReferenceAllocationRetry;

class CreateLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
        private LeaseStatusValidator $leaseStatusValidator,
        private GenerateInvoices $generateInvoices,
        private MoneyConverter $money,
        private ReferenceAllocationRetry $referenceAllocationRetry,
    ) {}

    public function execute(Unit|Property $target, CreateLeaseData $data): mixed
    {
        if ($target instanceof Property) {
            return $this->executeForProperty($target, $data);
        }

        $tenantIds = array_values(array_unique($data->tenantIds));

        return $this->referenceAllocationRetry->run(function () use ($target, $data, $tenantIds) {
            $property = Property::query()->lockForUpdate()->findOrFail($target->property_id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($target->id);

            abort_unless($unit->property_id === $property->id, 422, __('The unit does not belong to this property.'));

            abort_unless($property->rental_mode->supportsUnitInventory(), 404);
            abort_if($property->hasActiveWholePropertyLease(), 422, __('This property is already leased as a whole property.'));

            $activeRates = $unit->activeRates()->lockForUpdate()->get();
            $unitRate = $data->unitRateId === null
                ? null
                : $activeRates->firstWhere('id', $data->unitRateId);
            $preferredCurrency = $this->money->normalizeCurrency();
            $effectiveRate = $unitRate ?? ($activeRates->first(
                fn (UnitRate $rate): bool => $rate->currency === $preferredCurrency,
            ) ?? $activeRates->first());

            abort_if(
                $data->unitRateId !== null && $unitRate === null,
                422,
                __('The selected rate does not belong to this unit.'),
            );

            Tenant::query()->whereKey($tenantIds)->lockForUpdate()->get();

            abort_if(in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true), 422, __('This unit is not available for lease.'));

            $existingLease = $unit->leases()->active()->lockForUpdate()->first();
            if ($existingLease) {
                $this->ensureExistingLeaseTermsMatch($existingLease, $unitRate, $data);

                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');
                $newTenantIds = array_diff($tenantIds, $existingTenantIds->all());

                $this->ensureTenantsDoNotHaveActiveLease($newTenantIds);

                abort_if(! $this->occupancy->canAccommodate($unit, count($newTenantIds)), 422, __('Unit capacity exceeded. Unit can only hold :capacity occupants.', ['capacity' => $unit->capacity]));

                foreach ($newTenantIds as $tenantId) {
                    $existingLease->tenants()->attach($tenantId, ['is_primary' => false]);
                }

                $unit->update(['status' => UnitStatus::Occupied]);

                return $existingLease;
            }

            abort_if(! $this->occupancy->canAccommodate($unit, count($tenantIds)), 422, __('Unit capacity exceeded. Unit can only hold :capacity occupants.', ['capacity' => $unit->capacity]));

            $this->ensureTenantsDoNotHaveActiveLease($tenantIds);

            $lease = $this->createLease($property->id, $unit->id, $effectiveRate, null, $data, $tenantIds);

            $unit->update(['status' => UnitStatus::Occupied]);

            return $lease;
        }, 'leases');
    }

    private function executeForProperty(Property $property, CreateLeaseData $data): Lease
    {
        $tenantIds = array_values(array_unique($data->tenantIds));

        return $this->referenceAllocationRetry->run(function () use ($property, $data, $tenantIds): Lease {
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);

            abort_unless($property->rental_mode->supportsWholePropertyRental(), 404);
            abort_if($property->activeLeases()->lockForUpdate()->exists(), 422, __('This property already has an active lease.'));

            $activeRates = $property->activePropertyRates()->lockForUpdate()->get();
            $propertyRate = $data->propertyRateId === null
                ? null
                : $activeRates->firstWhere('id', $data->propertyRateId);
            $preferredCurrency = $this->money->normalizeCurrency();
            $propertyRate ??= $activeRates->first(
                fn (PropertyRate $rate): bool => $rate->currency === $preferredCurrency,
            ) ?? $activeRates->first();

            abort_if(
                $data->propertyRateId !== null && $propertyRate === null,
                422,
                __('The selected rate does not belong to this property.'),
            );

            abort_if($propertyRate === null, 422, __('This property has no active rental rate.'));

            Tenant::query()->whereKey($tenantIds)->lockForUpdate()->get();
            $this->ensureTenantsDoNotHaveActiveLease($tenantIds);

            return $this->createLease($property->id, null, null, $propertyRate, $data, $tenantIds);
        }, 'leases');
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    private function createLease(
        int $propertyId,
        ?int $unitId,
        ?UnitRate $unitRate,
        ?PropertyRate $propertyRate,
        CreateLeaseData $data,
        array $tenantIds,
    ): Lease {
        try {
            $rate = $unitRate ?? $propertyRate;
            $rentAmount = $data->rentAmount ?? $rate?->amount;
            $currency = $rate?->currency ?? $this->money->normalizeCurrency();
            $rentAmount = $rentAmount === null
                ? null
                : $this->money->normalizeAmount((string) $rentAmount, $currency);
            $depositAmount = $data->depositAmount === null
                ? '0'
                : $this->money->normalizeAmount($data->depositAmount, $currency);
            $depositRefundAmount = $data->depositRefundAmount === null
                ? null
                : $this->money->normalizeAmount($data->depositRefundAmount, $currency);
        } catch (\InvalidArgumentException) {
            abort(422, __('The selected rental rate amount is invalid for its currency.'));
        }

        $isCustomPrice = $data->rentAmount !== null
            && $rate
            && $this->money->compare($data->rentAmount, (string) $rate->amount) !== 0;

        $lease = Lease::query()->create([
            'property_id' => $propertyId,
            'unit_id' => $unitId,
            'primary_tenant_id' => $tenantIds[0],
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'rent_amount' => $rentAmount,
            'currency' => $currency,
            'billing_interval' => $rate?->billing_interval ?? $data->billingInterval ?? 1,
            'billing_unit' => $rate?->billing_unit ?? $data->billingUnit ?? 'month',
            'billing_strategy' => $data->billingStrategy ?? 'advance',
            'is_custom_price' => $isCustomPrice,
            'unit_rate_id' => $unitRate?->id,
            'property_rate_id' => $propertyRate?->id,
            'deposit_amount' => $depositAmount,
            'deposit_paid_at' => $data->depositPaidAt,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositRefundedAt,
            'rent_due_day' => $data->rentDueDay ?? 1,
            'status' => LeaseStatus::Active,
            'notes' => $data->notes,
        ]);

        foreach ($tenantIds as $index => $tenantId) {
            $lease->tenants()->attach($tenantId, ['is_primary' => $index === 0]);
        }

        $this->generateInvoices->execute($lease);

        return $lease;
    }

    private function ensureExistingLeaseTermsMatch(Lease $lease, ?UnitRate $unitRate, CreateLeaseData $data): void
    {
        if ($data->unitRateId !== null) {
            abort_if(
                $lease->unit_rate_id !== $data->unitRateId,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
            abort_if(
                $unitRate?->currency !== $lease->currency,
                422,
                __('The selected rate currency does not match the existing lease.'),
            );
        }

        if ($data->rentAmount !== null) {
            abort_if(
                $lease->rent_amount === null
                    || $this->money->compare($data->rentAmount, (string) $lease->rent_amount) !== 0,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
        }

        if ($data->billingInterval !== null) {
            abort_if(
                $data->billingInterval !== $lease->billing_interval,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
        }

        if ($data->billingUnit !== null) {
            abort_if(
                $data->billingUnit !== $lease->billing_unit?->value,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
        }

        if ($data->billingStrategy !== null) {
            abort_if(
                $data->billingStrategy !== $lease->billing_strategy?->value,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
        }

        if ($data->rentDueDay !== null) {
            abort_if(
                $data->rentDueDay !== $lease->rent_due_day,
                422,
                __('The existing lease terms cannot be changed while adding a tenant.'),
            );
        }

        abort_if(
            $data->startDate !== $lease->start_date?->toDateString(),
            422,
            __('The existing lease terms cannot be changed while adding a tenant.'),
        );
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    private function ensureTenantsDoNotHaveActiveLease(array $tenantIds): void
    {
        abort_if(
            $tenantIds !== [] && Lease::query()
                ->active()
                ->whereHas('tenants', fn ($query) => $query->whereIn('tenants.id', $tenantIds))
                ->exists(),
            422,
            __('A tenant already has an active lease.'),
        );
    }
}
