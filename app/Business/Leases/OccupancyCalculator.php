<?php

namespace App\Business\Leases;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class OccupancyCalculator
{
    public function activeOccupantCount(Unit $unit): int
    {
        return DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.unit_id', $unit->id)
            ->where('leases.status', 'active')
            ->count();
    }

    public function canAccommodate(Unit $unit, int $incomingCount): bool
    {
        return ($this->activeOccupantCount($unit) + $incomingCount) <= $unit->capacity;
    }
}
