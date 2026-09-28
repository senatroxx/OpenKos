<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->application()->where('user_id', $user->id)->exists() || $this->operator($user);
    }

    public function confirm(User $user, Reservation $reservation): bool
    {
        return $this->operator($user) && $reservation->status === ReservationStatus::Pending;
    }

    public function reject(User $user, Reservation $reservation): bool
    {
        return $this->operator($user) && $reservation->status === ReservationStatus::Pending;
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return ($this->operator($user) || $reservation->application()->where('user_id', $user->id)->exists())
            && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);
    }

    public function createLease(User $user, Reservation $reservation): bool
    {
        return $this->operator($user) && $reservation->status === ReservationStatus::Confirmed;
    }

    private function operator(User $user): bool
    {
        return $user->isOwner() || $user->can('tenants.view');
    }
}
