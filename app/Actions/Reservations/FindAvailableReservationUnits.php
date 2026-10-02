<?php

namespace App\Actions\Reservations;

use App\Business\Leases\OccupancyCalculator;
use App\Models\Application;
use App\Models\Unit;
use App\Repositories\ReservationRepository;
use Illuminate\Support\Collection;

class FindAvailableReservationUnits
{
    public function __construct(
        private ReservationRepository $reservations,
        private OccupancyCalculator $occupancy,
    ) {}

    /** @return Collection<int, Unit> */
    public function execute(Application $application, string $moveInDate): Collection
    {
        return $this->reservations->unitCandidatesForReservation($application, $moveInDate)
            ->filter(fn (Unit $unit): bool => $this->occupancy->canAccommodate(
                $unit->capacity,
                (int) $unit->occupied_slots,
                1,
                (int) $unit->reserved_slots,
            ))
            ->values();
    }
}
