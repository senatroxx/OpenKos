<?php

namespace App\Business\Leases;

use App\Models\Room;
use Illuminate\Support\Facades\DB;

class OccupancyCalculator
{
    public function activeOccupantCount(Room $room): int
    {
        return DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.room_id', $room->id)
            ->where('leases.status', 'active')
            ->count();
    }

    public function canAccommodate(Room $room, int $incomingCount): bool
    {
        return ($this->activeOccupantCount($room) + $incomingCount) <= $room->capacity;
    }
}
