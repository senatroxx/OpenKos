<?php

namespace App\Business\Leases;

class OccupancyCalculator
{
    public function canAccommodate(int $capacity, int $activeOccupantCount, int $incomingCount): bool
    {
        return ($activeOccupantCount + $incomingCount) <= $capacity;
    }
}
