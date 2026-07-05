<?php

namespace App\Actions\Maintenance;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\LeaseUnitHistory;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class BlockUnit
{
    public function execute(int $unitId, ?int $moveToUnitId): void
    {
        $unit = Unit::lockForUpdate()->findOrFail($unitId);
        $activeLease = $unit->leases()->where('status', LeaseStatus::Active->value)->first();

        if ($activeLease && $moveToUnitId) {
            $this->transferOccupants($unit, $activeLease, $moveToUnitId);
        } elseif ($activeLease) {
            $unit->update(['status' => UnitStatus::Maintenance]);
        } else {
            $unit->update(['status' => UnitStatus::Maintenance]);
        }
    }

    private function transferOccupants(Unit $unit, mixed $activeLease, int $targetUnitId): void
    {
        $targetUnit = Unit::lockForUpdate()->findOrFail($targetUnitId);

        abort_if($targetUnit->status === UnitStatus::Maintenance, 422, __('Target unit is under maintenance.'));
        abort_if($targetUnit->id === $unit->id, 422, __('Cannot move to the same unit.'));

        $targetHasLease = $targetUnit->leases()->where('status', LeaseStatus::Active->value)->exists();
        abort_if($targetHasLease, 422, __('Target unit already has an active lease.'));

        $activeLease->load('tenants');

        $activeTenantsCount = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.unit_id', $targetUnit->id)
            ->where('leases.status', LeaseStatus::Active->value)
            ->count();

        $incomingCount = $activeLease->tenants->count();

        abort_if(($activeTenantsCount + $incomingCount) > $targetUnit->capacity, 422, __('Target unit capacity exceeded.'));

        LeaseUnitHistory::create([
            'lease_id' => $activeLease->id,
            'from_unit_id' => $unit->id,
            'to_unit_id' => $targetUnit->id,
            'transferred_by' => auth()->id(),
            'reason' => 'maintenance',
            'notes' => __('Unit :from blocked for maintenance. Transfer to :to.', ['from' => $unit->name, 'to' => $targetUnit->name]),
            'effective_date' => now(),
        ]);

        $notes = $activeLease->notes
            ? $activeLease->notes."\n".__('Transferred from :from to :to (maintenance)', ['from' => $unit->name, 'to' => $targetUnit->name])
            : __('Transferred from :from to :to (maintenance)', ['from' => $unit->name, 'to' => $targetUnit->name]);

        $activeLease->update([
            'unit_id' => $targetUnit->id,
            'notes' => $notes,
        ]);

        $targetUnit->update(['status' => UnitStatus::Occupied]);
        $unit->update(['status' => UnitStatus::Maintenance]);
    }
}
