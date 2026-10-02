<?php

namespace App\Business\Reservations;

use App\Enums\ReservationStatus;

class ReservationTransitionValidator
{
    public function canTransition(ReservationStatus $current, ReservationStatus $next): bool
    {
        return match ($current) {
            ReservationStatus::Pending => in_array($next, [
                ReservationStatus::Confirmed,
                ReservationStatus::Rejected,
                ReservationStatus::Cancelled,
            ], true),
            ReservationStatus::Confirmed => in_array($next, [
                ReservationStatus::Cancelled,
                ReservationStatus::Expired,
                ReservationStatus::Converted,
            ], true),
            default => false,
        };
    }
}
