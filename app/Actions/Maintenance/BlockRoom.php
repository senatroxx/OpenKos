<?php

namespace App\Actions\Maintenance;

use App\Enums\RoomStatus;
use App\Models\LeaseRoomHistory;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class BlockRoom
{
    public function execute(int $roomId, ?int $moveToRoomId): void
    {
        $room = Room::lockForUpdate()->findOrFail($roomId);
        $activeLease = $room->leases()->where('status', 'active')->first();

        if ($activeLease && $moveToRoomId) {
            $this->transferOccupants($room, $activeLease, $moveToRoomId);
        } elseif ($activeLease) {
            $room->update(['status' => RoomStatus::Maintenance]);
        } else {
            $room->update(['status' => RoomStatus::Maintenance]);
        }
    }

    private function transferOccupants(Room $room, mixed $activeLease, int $targetRoomId): void
    {
        $targetRoom = Room::lockForUpdate()->findOrFail($targetRoomId);

        abort_if($targetRoom->status === RoomStatus::Maintenance, 422, __('Target room is under maintenance.'));
        abort_if($targetRoom->id === $room->id, 422, __('Cannot move to the same room.'));

        $targetHasLease = $targetRoom->leases()->where('status', 'active')->exists();
        abort_if($targetHasLease, 422, __('Target room already has an active lease.'));

        $activeLease->load('tenants');

        $activeTenantsCount = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.room_id', $targetRoom->id)
            ->where('leases.status', 'active')
            ->count();

        $incomingCount = $activeLease->tenants->count();

        abort_if(($activeTenantsCount + $incomingCount) > $targetRoom->capacity, 422, __('Target room capacity exceeded.'));

        LeaseRoomHistory::create([
            'lease_id' => $activeLease->id,
            'from_room_id' => $room->id,
            'to_room_id' => $targetRoom->id,
            'transferred_by' => auth()->id(),
            'reason' => 'maintenance',
            'notes' => __('Room :from blocked for maintenance. Transfer to :to.', ['from' => $room->name, 'to' => $targetRoom->name]),
            'effective_date' => now(),
        ]);

        $notes = $activeLease->notes
            ? $activeLease->notes."\n".__('Transferred from :from to :to (maintenance)', ['from' => $room->name, 'to' => $targetRoom->name])
            : __('Transferred from :from to :to (maintenance)', ['from' => $room->name, 'to' => $targetRoom->name]);

        $activeLease->update([
            'room_id' => $targetRoom->id,
            'notes' => $notes,
        ]);

        $targetRoom->update(['status' => RoomStatus::Occupied]);
        $room->update(['status' => RoomStatus::Maintenance]);
    }
}
