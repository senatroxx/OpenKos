<?php

namespace App\Actions\Leases;

use App\Business\Leases\LeaseStatusValidator;
use App\Data\Lease\TerminateLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Exceptions\LeaseTerminationConflict;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Results\Lease\TerminateLeaseResult;
use Illuminate\Support\Facades\DB;

final class TerminateLease
{
    public function __construct(private LeaseStatusValidator $leaseStatusValidator) {}

    public function execute(Property $property, Unit $unit, Lease $lease, TerminateLeaseData $data): TerminateLeaseResult
    {
        try {
            return DB::transaction(function () use ($property, $unit, $lease, $data): TerminateLeaseResult {
                $lockedProperty = Property::query()->lockForUpdate()->findOrFail($property->id);
                $lockedUnit = Unit::query()->lockForUpdate()->findOrFail($unit->id);
                $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);
                $oldLeaseStatus = $lockedLease->status;

                if ((int) $lockedLease->property_id !== $lockedProperty->id || (int) $lockedLease->unit_id !== $lockedUnit->id) {
                    throw new LeaseTerminationConflict(__('Lease is no longer assigned to this property or unit.'));
                }

                $this->leaseStatusValidator->validate($oldLeaseStatus, LeaseStatus::Terminated);

                $oldUnitStatus = $lockedUnit->status;
                $lockedLease->update([
                    'end_date' => now(),
                    'status' => LeaseStatus::Terminated,
                    'termination_date' => now(),
                    'termination_reason' => $data->reason,
                ]);

                $lockedUnit->unsetRelation('leases');
                if ($lockedUnit->leases()->active()->doesntExist() && $lockedUnit->status !== UnitStatus::Maintenance) {
                    $lockedUnit->update(['status' => UnitStatus::Available]);
                }

                return TerminateLeaseResult::success(
                    $lockedLease,
                    $oldLeaseStatus,
                    $lockedUnit,
                    $oldUnitStatus,
                    $lockedUnit->status,
                );
            });
        } catch (LeaseTerminationConflict $exception) {
            return TerminateLeaseResult::error($exception->getMessage());
        }
    }
}
