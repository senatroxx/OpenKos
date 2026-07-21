<?php

namespace App\Actions\Leases;

use App\Actions\Invoices\GenerateInvoices;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\CreateLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
use Illuminate\Support\Facades\DB;

class CreateLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
        private LeaseStatusValidator $leaseStatusValidator,
        private GenerateInvoices $generateInvoices,
    ) {}

    public function execute(Unit $unit, CreateLeaseData $data): mixed
    {
        $tenantIds = array_values(array_unique($data->tenantIds));

        return DB::transaction(function () use ($unit, $data, $tenantIds) {
            $unit = Unit::lockForUpdate()->findOrFail($unit->id);
            Tenant::query()->whereKey($tenantIds)->lockForUpdate()->get();

            abort_if(in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true), 422, __('This unit is not available for lease.'));

            $existingLease = $unit->leases()->where('status', LeaseStatus::Active->value)->first();
            $activeTenantsCount = $this->occupancy->activeOccupantCount($unit);

            if ($existingLease) {
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

            $unitRate = $data->unitRateId ? UnitRate::find($data->unitRateId) : null;
            $rentAmount = $data->rentAmount ?? $unitRate?->amount ?? $unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount');
            $isCustomPrice = $data->rentAmount !== null && $unitRate && (float) $data->rentAmount !== (float) $unitRate->amount;

            $primaryTenantId = $tenantIds[0];

            $lease = $unit->leases()->create([
                'primary_tenant_id' => $primaryTenantId,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'rent_amount' => $rentAmount,
                'billing_interval' => $data->billingInterval ?? $unitRate?->billing_interval ?? 1,
                'billing_unit' => $data->billingUnit ?? $unitRate?->billing_unit ?? 'month',
                'billing_strategy' => $data->billingStrategy ?? 'advance',
                'is_custom_price' => $isCustomPrice,
                'unit_rate_id' => $data->unitRateId,
                'deposit_amount' => $data->depositAmount ?? 0,
                'deposit_paid_at' => $data->depositPaidAt,
                'deposit_refund_amount' => $data->depositRefundAmount,
                'deposit_refunded_at' => $data->depositRefundedAt,
                'rent_due_day' => $data->rentDueDay ?? 1,
                'status' => LeaseStatus::Active,
                'notes' => $data->notes,
            ]);

            foreach ($tenantIds as $index => $tenantId) {
                $lease->tenants()->attach($tenantId, ['is_primary' => $index === 0]);
            }

            $unit->update(['status' => UnitStatus::Occupied]);

            $this->generateInvoices->execute($lease);

            return $lease;
        });
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    private function ensureTenantsDoNotHaveActiveLease(array $tenantIds): void
    {
        abort_if(
            $tenantIds !== [] && Lease::query()
                ->where('status', LeaseStatus::Active->value)
                ->whereHas('tenants', fn ($query) => $query->whereIn('tenants.id', $tenantIds))
                ->exists(),
            422,
            __('A tenant already has an active lease.'),
        );
    }
}
