<?php

namespace App\Actions\Leases;

use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Events\Lease\LeaseStatusChanged;
use App\Models\Lease;
use App\Models\Unit;
use App\Results\Lease\MoveOutLeaseResult;
use Illuminate\Support\Facades\DB;

class MoveOutLease
{
    public function __construct(
        private OccupancyCalculator $occupancy,
        private LeaseStatusValidator $leaseStatusValidator,
    ) {}

    public function execute(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        return DB::transaction(function () use ($lease, $data) {
            if ($data->moveToAnotherUnit) {
                return $this->transfer($lease, $data);
            }

            return $this->terminate($lease, $data);
        });
    }

    private function terminate(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $oldStatus = $lease->status;

        $this->leaseStatusValidator->validate($oldStatus, LeaseStatus::Terminated);

        $depositRefundAmount = $data->depositReturned
            ? ($data->depositRefundAmount ?? $lease->deposit_amount)
            : null;

        $oldUnit = $lease->unit;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => LeaseStatus::Terminated,
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $oldUnit->unsetRelation('leases');

        LeaseStatusChanged::dispatch($lease, $oldStatus, LeaseStatus::Terminated);

        if ($oldUnit->leases()->where('status', LeaseStatus::Active->value)->doesntExist() && $oldUnit->status !== UnitStatus::Maintenance) {
            $oldUnit->update(['status' => UnitStatus::Available]);
        }

        return MoveOutLeaseResult::success($lease);
    }

    private function transfer(Lease $lease, MoveOutLeaseData $data): MoveOutLeaseResult
    {
        $oldStatus = $lease->status; // ponytail: captured before validator call

        $this->leaseStatusValidator->validate($oldStatus, LeaseStatus::Terminated);

        $targetUnit = Unit::lockForUpdate()->findOrFail($data->targetUnitId);

        abort_if($targetUnit->status === UnitStatus::Maintenance, 422, __('Target unit is under maintenance.'));

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

        $oldUnit = $lease->unit;

        $lease->update([
            'end_date' => $data->endDate,
            'status' => LeaseStatus::Terminated,
            'termination_date' => $data->terminationDate,
            'termination_reason' => $data->reason,
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $data->depositReturned ? now() : null,
            'notes' => $data->notes ?? $lease->notes,
        ]);

        $oldUnit->unsetRelation('leases');

        if ($oldUnit->leases()->where('status', LeaseStatus::Active->value)->doesntExist() && $oldUnit->status !== UnitStatus::Maintenance) {
            $oldUnit->update(['status' => UnitStatus::Available]);
        }

        $lease->load('tenants');
        $existingLease = $targetUnit->leases()->where('status', LeaseStatus::Active->value)->first();

        if ($existingLease) {
            $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

            foreach ($lease->tenants as $tenant) {
                if (! $existingTenantIds->contains($tenant->id)) {
                    $existingLease->tenants()->attach($tenant->id, [
                        'is_primary' => false,
                    ]);
                }
            }
        } else {
            $matchingRate = $targetUnit->rates()
                ->where('billing_interval', $lease->billing_interval)
                ->where('billing_unit', $lease->billing_unit)
                ->first();

            $newLease = $targetUnit->leases()->create([
                'primary_tenant_id' => $lease->primary_tenant_id,
                'start_date' => $data->endDate,
                'rent_amount' => $lease->rent_amount,
                'billing_interval' => $lease->billing_interval ?? 1,
                'billing_unit' => $lease->billing_unit ?? 'month',
                'is_custom_price' => $lease->is_custom_price,
                'unit_rate_id' => $matchingRate?->id,
                'deposit_amount' => $lease->deposit_amount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'deposit_refund_amount' => $data->carryDepositRefund ? $lease->deposit_refund_amount : null,
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
        }

        $targetUnit->update(['status' => UnitStatus::Occupied]);

        return MoveOutLeaseResult::success($lease, $newLease ?? null);
    }
}
