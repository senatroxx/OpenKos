<?php

namespace App\Actions\Reservations;

use App\Business\Reservations\ReservationTransitionValidator;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function __construct(private ReservationTransitionValidator $transitions) {}

    public function execute(User $actor, Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($actor, $reservation): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($this->transitions->canTransition($reservation->status, ReservationStatus::Cancelled), 422, __('Only pending or confirmed reservations can be cancelled.'));

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            return $reservation->fresh();
        });
    }
}
