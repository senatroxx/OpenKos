<?php

namespace App\Actions\Leases;

use App\Actions\Invoices\GenerateInvoices;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Unit;
use App\Results\Lease\MoveOutLeaseResult;
use App\Services\Payments\MoneyConverter;
use App\Services\ReferenceAllocationRetry;
use Illuminate\Support\Collection;

class MoveOutLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
        private LeaseStatusValidator $leaseStatusValidator,
        private GenerateInvoices $generateInvoices,
        private MoneyConverter $money,
        private ReferenceAllocationRetry $referenceAllocationRetry,
    ) {}

    private function cancelFutureInvoices(Lease $lease): void
    {
        // Cancel invoices whose period starts after the lease ends (actual
        // move-out date, not the termination record date which is always
        // today). The lease's end_date has already been updated by this point.
        $lease->invoices()
            ->where('status', InvoiceStatus::Pending->value)
            ->where('amount_paid', 0)
            ->whereDate('period_start', '>', $lease->end_date)
            ->get()
            ->each->update(['status' => InvoiceStatus::Cancelled]);
    }

    public function execute(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        return $this->referenceAllocationRetry->run(function () use ($lease, $data) {
            $unitIds = [$lease->unit_id];
            if ($data->moveToAnotherUnit) {
                abort_unless($data->targetUnitId !== null, 422, __('Target unit is required.'));
                $unitIds[] = $data->targetUnitId;
            }

            $lockedUnits = $this->lockUnits($unitIds);
            $sourceUnit = $lockedUnits->get($lease->unit_id);
            $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            abort_unless($sourceUnit && (int) $lockedLease->unit_id === $sourceUnit->id, 422, __('Lease is no longer assigned to this unit.'));

            if ($data->moveToAnotherUnit) {
                $targetUnit = $lockedUnits->get($data->targetUnitId);
                abort_unless($targetUnit, 404);

                return $this->transfer($lockedLease, $sourceUnit, $targetUnit, $data);
            }

            return $this->terminate($lockedLease, $sourceUnit, $data);
        }, 'leases');
    }

    /**
     * @param  array<int, int|null>  $unitIds
     * @return Collection<int, Unit>
     */
    private function lockUnits(array $unitIds): Collection
    {
        $unitIds = array_values(array_unique(array_filter($unitIds, fn (?int $unitId): bool => $unitId !== null)));
        sort($unitIds);

        $units = Unit::query()
            ->whereKey($unitIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        abort_unless($units->count() === count($unitIds), 404);

        return $units;
    }

    private function terminate(Lease $lease, Unit $oldUnit, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $oldLeaseStatus = $lease->status;
        $oldSourceStatus = $oldUnit->status;

        $this->leaseStatusValidator->validate($oldLeaseStatus, LeaseStatus::Terminated);

        $depositRefundAmount = $data->depositReturned
            ? ($data->depositRefundAmount ?? $lease->deposit_amount)
            : null;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => LeaseStatus::Terminated,
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $this->cancelFutureInvoices($lease);

        if ($oldUnit->leases()->where('status', LeaseStatus::Active->value)->doesntExist() && $oldUnit->status !== UnitStatus::Maintenance) {
            $oldUnit->update(['status' => UnitStatus::Available]);
        }

        return MoveOutLeaseResult::success(
            oldLease: $lease,
            sourceUnit: $oldUnit,
            oldLeaseStatus: $oldLeaseStatus,
            oldSourceStatus: $oldSourceStatus,
            newSourceStatus: $oldUnit->status,
        );
    }

    private function transfer(Lease $lease, Unit $oldUnit, Unit $targetUnit, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $oldLeaseStatus = $lease->status;
        $oldSourceStatus = $oldUnit->status;
        $oldTargetStatus = $targetUnit->status;

        $this->leaseStatusValidator->validate($oldLeaseStatus, LeaseStatus::Terminated);

        abort_if($targetUnit->id === $oldUnit->id, 422, __('Cannot move to the same unit.'));
        abort_if(in_array($targetUnit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true), 422, __('Target unit is not available for lease.'));

        $lease->load('tenants');

        $incomingTenantIds = $lease->tenants->pluck('id')->toArray();
        $existingLease = $targetUnit->leases()->where('status', LeaseStatus::Active->value)->first();

        $incomingCount = $existingLease
            ? count(array_diff($incomingTenantIds, $existingLease->tenants()->pluck('tenants.id')->all()))
            : count($incomingTenantIds);

        if (! $this->occupancy->canAccommodate($targetUnit, $incomingCount)) {
            abort(422, __('Unit capacity exceeded. Target unit can only hold :capacity occupants.', ['capacity' => $targetUnit->capacity]));
        }

        $depositRefundAmount = $data->depositReturned
            ? ($data->depositRefundAmount ?? $lease->deposit_amount)
            : null;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => LeaseStatus::Terminated,
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $this->cancelFutureInvoices($lease);

        $oldUnit->unsetRelation('leases');

        if ($oldUnit->leases()->where('status', LeaseStatus::Active->value)->doesntExist() && $oldUnit->status !== UnitStatus::Maintenance) {
            $oldUnit->update(['status' => UnitStatus::Available]);
        }

        $lease->load('tenants');
        $existingLease = $targetUnit->leases()->where('status', LeaseStatus::Active->value)->first();

        $newLease = null;
        if ($existingLease) {
            abort_if(
                $existingLease->currency !== $lease->currency,
                422,
                __('Cannot merge leases with different currencies.'),
            );

            $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

            foreach ($lease->tenants as $tenant) {
                if (! $existingTenantIds->contains($tenant->id)) {
                    $existingLease->tenants()->attach($tenant->id, [
                        'is_primary' => false,
                    ]);
                }
            }
        } else {
            $matchingRates = $targetUnit->rates()
                ->where('is_active', true)
                ->where('billing_interval', $lease->billing_interval)
                ->where('billing_unit', $lease->billing_unit)
                ->get();
            $matchingRate = $matchingRates->first(
                fn ($rate): bool => $rate->currency === $lease->currency,
            );

            abort_if(
                $matchingRates->isNotEmpty() && $matchingRate === null,
                422,
                __('The target unit rate currency must match the existing lease currency.'),
            );

            try {
                $rentAmount = $lease->rent_amount === null
                    ? null
                    : $this->money->normalizeAmount((string) $lease->rent_amount, $lease->currency);
                $depositAmount = $lease->deposit_amount === null
                    ? null
                    : $this->money->normalizeAmount((string) $lease->deposit_amount, $lease->currency);
                $depositRefundAmount = $data->carryDepositRefund && $lease->deposit_refund_amount !== null
                    ? $this->money->normalizeAmount((string) $lease->deposit_refund_amount, $lease->currency)
                    : null;
            } catch (\InvalidArgumentException) {
                abort(422, __('The existing lease amount is invalid for its currency.'));
            }

            $newLease = $targetUnit->leases()->create([
                'primary_tenant_id' => $lease->primary_tenant_id,
                'start_date' => $data->endDate,
                'rent_amount' => $rentAmount,
                'currency' => $lease->currency,
                'billing_interval' => $lease->billing_interval ?? 1,
                'billing_unit' => $lease->billing_unit ?? 'month',
                'billing_strategy' => $lease->billing_strategy,
                'is_custom_price' => $lease->is_custom_price,
                'unit_rate_id' => $matchingRate?->id,
                'deposit_amount' => $depositAmount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'deposit_refund_amount' => $depositRefundAmount,
                'deposit_refunded_at' => $data->carryDepositRefund ? $lease->deposit_refunded_at : null,
                'rent_due_day' => $lease->rent_due_day,
                'status' => LeaseStatus::Active,
                'notes' => 'Moved from unit '.$oldUnit->name.' on '.now()->format('Y-m-d'),
            ]);

            foreach ($lease->tenants as $tenant) {
                $newLease->tenants()->attach($tenant->id, [
                    'is_primary' => $tenant->id === $lease->primary_tenant_id,
                ]);
            }

            $this->generateInvoices->execute($newLease);
        }

        $targetUnit->update(['status' => UnitStatus::Occupied]);

        return MoveOutLeaseResult::success(
            oldLease: $lease,
            newLease: $newLease,
            sourceUnit: $oldUnit,
            targetUnit: $targetUnit,
            oldLeaseStatus: $oldLeaseStatus,
            oldSourceStatus: $oldSourceStatus,
            newSourceStatus: $oldUnit->status,
            oldTargetStatus: $oldTargetStatus,
            newTargetStatus: $targetUnit->status,
        );
    }
}
