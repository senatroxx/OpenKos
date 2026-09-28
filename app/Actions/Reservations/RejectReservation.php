<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectReservation
{
    public function execute(User $operator, Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($operator, $reservation): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($reservation->status === ReservationStatus::Pending, 422, __('Only pending reservations can be rejected.'));

            $reservation->update([
                'status' => ReservationStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $operator->id,
            ]);

            return $reservation->fresh();
        });
    }
}
