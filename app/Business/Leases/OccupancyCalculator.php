<?php

namespace App\Business\Leases;

class OccupancyCalculator
{
    public function canAccommodate(
        int $capacity,
        int $activeOccupantCount,
        int $incomingCount,
        int $reservedOccupantCount = 0,
    ): bool {
        return ($activeOccupantCount + $reservedOccupantCount + $incomingCount) <= $capacity;
    }
}
