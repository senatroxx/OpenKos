<?php

namespace App\Actions\Maintenance;

use App\Enums\RoomStatus;
use App\Models\Lease;
use App\Models\LeaseRoomHistory;
use App\Models\MaintenanceTicket;
use App\Models\Room;

class ResolveTicket
{
    public function execute(MaintenanceTicket $ticket, bool $moveBack): void
    {
        if (! $ticket->room_id) {
            return;
        }

        $staysMaintenance = MaintenanceTicket::query()
            ->where('room_id', $ticket->room_id)
            ->whereKeyNot($ticket->id)
            ->whereNotIn('status', ['resolved', 'cancelled'])
            ->exists();

        if ($staysMaintenance) {
            return;
        }

        $room = Room::lockForUpdate()->findOrFail($ticket->room_id);

        if ($moveBack) {
            $this->moveOccupantsBack($ticket, $room);
        }

        $hasActiveLease = $room->leases()->where('status', 'active')->exists();
        $newRoomStatus = $hasActiveLease ? RoomStatus::Occupied : RoomStatus::Available;
        $room->update(['status' => $newRoomStatus]);
    }

    private function moveOccupantsBack(MaintenanceTicket $ticket, Room $room): void
    {
        $transfer = LeaseRoomHistory::query()
            ->where('from_room_id', $ticket->room_id)
            ->where('reason', 'maintenance')
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $transfer) {
            return;
        }

        $movedLease = Lease::where('status', 'active')
            ->where('room_id', $transfer->to_room_id)
            ->first();

        if (! $movedLease) {
            return;
        }

        $targetHasLease = $room->leases()
            ->where('status', 'active')
            ->whereKeyNot($movedLease->id)
            ->exists();

        if ($targetHasLease) {
            return;
        }

        LeaseRoomHistory::create([
            'lease_id' => $movedLease->id,
            'from_room_id' => $transfer->to_room_id,
            'to_room_id' => $room->id,
            'transferred_by' => auth()->id(),
            'reason' => 'maintenance_resolved',
            'notes' => __('Ticket :ref resolved. Transfer back to :room.', ['ref' => $ticket->reference, 'room' => $room->name]),
            'effective_date' => now(),
        ]);

        $notes = $movedLease->notes
            ? $movedLease->notes."\n".__('Transferred back to :room (maintenance resolved)', ['room' => $room->name])
            : __('Transferred back to :room (maintenance resolved)', ['room' => $room->name]);

        $movedLease->update([
            'room_id' => $room->id,
            'notes' => $notes,
        ]);

        $targetRoom = Room::lockForUpdate()->findOrFail($transfer->to_room_id);
        $targetRoomStillOccupied = $targetRoom->leases()->where('status', 'active')->exists();
        $targetRoom->update(['status' => $targetRoomStillOccupied ? RoomStatus::Occupied : RoomStatus::Available]);
    }
}
