<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function execute(User $actor, Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($actor, $reservation): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless(in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true), 422, __('Only pending or confirmed reservations can be cancelled.'));

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            return $reservation->fresh();
        });
    }
}
