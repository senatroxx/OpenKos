<?php

namespace App\Data\Reservation;

final readonly class RequestReservationData
{
    public function __construct(public string $moveInDate) {}
}
