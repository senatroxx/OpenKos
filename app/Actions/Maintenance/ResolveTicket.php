<?php

namespace App\Actions\Maintenance;

use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Pricing\EffectiveUnitRateResolver;
use Illuminate\Support\Facades\DB;

class ResolveTicket
{
    public function __construct(private EffectiveUnitRateResolver $effectiveUnitRateResolver) {}

    /**
     * @return array<int, array{unit: Unit, from: UnitStatus}>
     */
    public function execute(MaintenanceTicket $ticket, bool $moveBack): array
    {
        return DB::transaction(function () use ($ticket, $moveBack): array {
            $property = Property::query()->lockForUpdate()->findOrFail($ticket->property_id);
            $ticket = MaintenanceTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless((int) $ticket->property_id === $property->id, 422, __('Maintenance ticket property has changed.'));

            if (! $ticket->unit_id) {
                return [];
            }

            $transfer = $moveBack
                ? LeaseUnitHistory::query()
                    ->where('from_unit_id', $ticket->unit_id)
                    ->where('reason', 'maintenance')
                    ->orderBy('effective_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first()
                : null;

            $unitIds = array_values(array_unique(array_filter([
                $ticket->unit_id,
                $transfer?->to_unit_id,
            ])));
            sort($unitIds);

            $lockedUnits = Unit::query()
                ->whereKey($unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($lockedUnits->has($ticket->unit_id), 404);

            $changes = $lockedUnits
                ->map(fn (Unit $unit): array => ['unit' => $unit, 'from' => $unit->status])
                ->all();

            $unit = $lockedUnits->get($ticket->unit_id);
            $staysMaintenance = MaintenanceTicket::query()
                ->where('unit_id', $ticket->unit_id)
                ->whereKeyNot($ticket->id)
                ->whereNotIn('status', ['resolved', 'cancelled'])
                ->exists();

            if ($staysMaintenance) {
                return $changes;
            }

            if ($moveBack && $transfer) {
                abort_unless($lockedUnits->has($transfer->to_unit_id), 404);
                $this->moveOccupantsBack($ticket, $unit, $lockedUnits->get($transfer->to_unit_id), $transfer);
            }

            $hasActiveLease = $unit->leases()->active()->exists();
            $unit->update(['status' => $hasActiveLease ? UnitStatus::Occupied : UnitStatus::Available]);

            return $changes;
        });
    }

    private function moveOccupantsBack(MaintenanceTicket $ticket, Unit $unit, Unit $targetUnit, LeaseUnitHistory $transfer): void
    {
        $movedLease = Lease::query()->active()
            ->where('unit_id', $transfer->to_unit_id)
            ->first();

        if (! $movedLease) {
            return;
        }

        $targetHasLease = $unit->leases()
            ->active()
            ->whereKeyNot($movedLease->id)
            ->exists();

        if ($targetHasLease) {
            return;
        }

        abort_unless($unit->property_id === $targetUnit->property_id, 422, __('Maintenance transfer crossed properties.'));
        $targetProperty = Property::query()->whereKey($unit->property_id)->lockForUpdate()->firstOrFail();
        abort_unless($targetProperty->rental_mode->supportsUnitInventory(), 404);

        $matching = $this->effectiveUnitRateResolver->find($unit, $movedLease->billing_interval, $movedLease->billing_unit, $movedLease->currency);
        $matchingRate = $matching['rate'] ?? null;

        abort_if(
            $this->effectiveUnitRateResolver->resolve($unit)->isNotEmpty() && $matchingRate === null,
            422,
            __('The target unit rate currency must match the existing lease currency.'),
        );

        LeaseUnitHistory::create([
            'lease_id' => $movedLease->id,
            'from_unit_id' => $transfer->to_unit_id,
            'to_unit_id' => $unit->id,
            'transferred_by' => auth()->id(),
            'reason' => 'maintenance_resolved',
            'notes' => __('Ticket :ref resolved. Transfer back to :unit.', ['ref' => $ticket->reference, 'unit' => $unit->name]),
            'effective_date' => now(),
        ]);

        $notes = $movedLease->notes
            ? $movedLease->notes."\n".__('Transferred back to :unit (maintenance resolved)', ['unit' => $unit->name])
            : __('Transferred back to :unit (maintenance resolved)', ['unit' => $unit->name]);

        $movedLease->update([
            'unit_id' => $unit->id,
            'property_id' => $targetProperty->id,
            'unit_rate_id' => ($matching['source'] ?? null) === 'unit' ? $matchingRate?->id : null,
            'unit_type_rate_id' => ($matching['source'] ?? null) === 'unit_type' ? $matchingRate?->id : null,
            'notes' => $notes,
        ]);

        $targetUnitStillOccupied = $targetUnit->leases()->active()->exists();
        $targetUnit->update(['status' => $targetUnitStillOccupied ? UnitStatus::Occupied : UnitStatus::Available]);
    }
}
