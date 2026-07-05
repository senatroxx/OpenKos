<?php

namespace App\Actions\Maintenance;

use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Unit;

class ResolveTicket
{
    public function execute(MaintenanceTicket $ticket, bool $moveBack): void
    {
        if (! $ticket->unit_id) {
            return;
        }

        $staysMaintenance = MaintenanceTicket::query()
            ->where('unit_id', $ticket->unit_id)
            ->whereKeyNot($ticket->id)
            ->whereNotIn('status', ['resolved', 'cancelled'])
            ->exists();

        if ($staysMaintenance) {
            return;
        }

        $unit = Unit::lockForUpdate()->findOrFail($ticket->unit_id);

        if ($moveBack) {
            $this->moveOccupantsBack($ticket, $unit);
        }

        $hasActiveLease = $unit->leases()->where('status', 'active')->exists();
        $newUnitStatus = $hasActiveLease ? UnitStatus::Occupied : UnitStatus::Available;
        $unit->update(['status' => $newUnitStatus]);
    }

    private function moveOccupantsBack(MaintenanceTicket $ticket, Unit $unit): void
    {
        $transfer = LeaseUnitHistory::query()
            ->where('from_unit_id', $ticket->unit_id)
            ->where('reason', 'maintenance')
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $transfer) {
            return;
        }

        $movedLease = Lease::where('status', 'active')
            ->where('unit_id', $transfer->to_unit_id)
            ->first();

        if (! $movedLease) {
            return;
        }

        $targetHasLease = $unit->leases()
            ->where('status', 'active')
            ->whereKeyNot($movedLease->id)
            ->exists();

        if ($targetHasLease) {
            return;
        }

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
            'notes' => $notes,
        ]);

        $targetUnit = Unit::lockForUpdate()->findOrFail($transfer->to_unit_id);
        $targetUnitStillOccupied = $targetUnit->leases()->where('status', 'active')->exists();
        $targetUnit->update(['status' => $targetUnitStillOccupied ? UnitStatus::Occupied : UnitStatus::Available]);
    }
}
