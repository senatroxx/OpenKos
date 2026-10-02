<?php

namespace App\Actions\Reservations;

use App\Business\Reservations\ReservationTransitionValidator;
use App\Enums\ReservationStatus;
use App\Repositories\ReservationRepository;
use Illuminate\Support\Facades\DB;

class ExpireReservations
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationTransitionValidator $transitions,
    ) {}

    public function execute(): int
    {
        $expiredAt = now();

        return DB::transaction(function () use ($expiredAt): int {
            $reservations = $this->reservations->confirmedReservationsExpiredBy($expiredAt);

            $expiredCount = 0;

            foreach ($reservations as $reservation) {
                if (! $this->transitions->canTransition($reservation->status, ReservationStatus::Expired)) {
                    continue;
                }

                $reservation->update([
                    'status' => ReservationStatus::Expired,
                    'expired_at' => $expiredAt,
                ]);
                $expiredCount++;
            }

            return $expiredCount;
        });
    }
}
