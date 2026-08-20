<?php

namespace App\Actions\Maintenance;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class BlockUnit
{
    /**
     * @return array<int, array{unit: Unit, from: UnitStatus}>
     */
    public function execute(int $unitId, ?int $moveToUnitId): array
    {
        return DB::transaction(function () use ($unitId, $moveToUnitId): array {
            $unitIds = array_values(array_unique(array_filter([$unitId, $moveToUnitId])));
            sort($unitIds);

            $lockedUnits = Unit::query()
                ->whereKey($unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($lockedUnits->has($unitId), 404);

            $changes = $lockedUnits
                ->map(fn (Unit $unit): array => ['unit' => $unit, 'from' => $unit->status])
                ->all();

            $unit = $lockedUnits->get($unitId);
            $activeLease = $unit->leases()->where('status', LeaseStatus::Active->value)->first();

            if ($activeLease && $moveToUnitId) {
                abort_unless($lockedUnits->has($moveToUnitId), 404);
                $this->transferOccupants($unit, $activeLease, $lockedUnits->get($moveToUnitId));
            } else {
                $unit->update(['status' => UnitStatus::Maintenance]);
            }

            return $changes;
        });
    }

    private function transferOccupants(Unit $unit, Lease $activeLease, Unit $targetUnit): void
    {
        abort_if(in_array($targetUnit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true), 422, __('Target unit is not available for lease.'));
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
