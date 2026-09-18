<?php

namespace App\Actions\Maintenance;

use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Pricing\EffectiveUnitRateResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BlockUnit
{
    public function __construct(private EffectiveUnitRateResolver $effectiveUnitRateResolver) {}

    /**
     * @return array<int, array{unit: Unit, from: UnitStatus}>
     */
    public function execute(int $unitId, ?int $moveToUnitId): array
    {
        return DB::transaction(function () use ($unitId, $moveToUnitId): array {
            $propertyIds = Unit::query()
                ->whereKey(array_values(array_unique(array_filter([$unitId, $moveToUnitId]))))
                ->pluck('property_id')
                ->unique()
                ->sort()
                ->values();
            $lockedProperties = Property::query()
                ->whereKey($propertyIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

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
            $activeLease = $unit->leases()->active()->lockForUpdate()->first();

            if ($activeLease && $moveToUnitId) {
                abort_unless($lockedUnits->has($moveToUnitId), 404);
                $this->transferOccupants($unit, $activeLease, $lockedUnits->get($moveToUnitId), $lockedProperties);
            } else {
                $unit->update(['status' => UnitStatus::Maintenance]);
            }

            return $changes;
        });
    }

    /** @param  Collection<int, Property>  $lockedProperties */
    private function transferOccupants(Unit $unit, Lease $activeLease, Unit $targetUnit, Collection $lockedProperties): void
    {
        abort_if(in_array($targetUnit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true), 422, __('Target unit is not available for lease.'));
        abort_if($targetUnit->id === $unit->id, 422, __('Cannot move to the same unit.'));

        $targetProperty = $lockedProperties->get($targetUnit->property_id);
        abort_unless($targetProperty, 404);
        abort_unless($targetProperty->rental_mode->supportsUnitInventory(), 404);

        $targetHasLease = $targetUnit->leases()->active()->exists();
        abort_if($targetHasLease, 422, __('Target unit already has an active lease.'));

        $matching = $this->effectiveUnitRateResolver->find($targetUnit, $activeLease->billing_interval, $activeLease->billing_unit, $activeLease->currency);
        $matchingRate = $matching['rate'] ?? null;

        abort_if(
            $this->effectiveUnitRateResolver->resolve($targetUnit)->isNotEmpty() && $matchingRate === null,
            422,
            __('The target unit rate currency must match the existing lease currency.'),
        );

        $activeLease->load('tenants');

        $activeTenantsCount = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.unit_id', $targetUnit->id)
            ->whereIn('leases.id', Lease::query()->active()->select('id'))
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
            'property_id' => $targetProperty->id,
            'unit_rate_id' => ($matching['source'] ?? null) === 'unit' ? $matchingRate?->id : null,
            'unit_type_rate_id' => ($matching['source'] ?? null) === 'unit_type' ? $matchingRate?->id : null,
            'notes' => $notes,
        ]);

        $targetUnit->update(['status' => UnitStatus::Occupied]);

        $unit->update(['status' => UnitStatus::Maintenance]);
    }
}
