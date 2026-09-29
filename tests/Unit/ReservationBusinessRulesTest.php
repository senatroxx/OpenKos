<?php

use App\Business\Reservations\ReservationTransitionValidator;
use App\Enums\ReservationStatus;

test('reservation transitions match the approved lifecycle', function () {
    $validator = new ReservationTransitionValidator;
    $allowedTransitions = [
        [ReservationStatus::Pending, ReservationStatus::Confirmed],
        [ReservationStatus::Pending, ReservationStatus::Rejected],
        [ReservationStatus::Pending, ReservationStatus::Cancelled],
        [ReservationStatus::Confirmed, ReservationStatus::Cancelled],
        [ReservationStatus::Confirmed, ReservationStatus::Expired],
        [ReservationStatus::Confirmed, ReservationStatus::Converted],
    ];

    foreach (ReservationStatus::cases() as $current) {
        foreach (ReservationStatus::cases() as $next) {
            expect($validator->canTransition($current, $next))
                ->toBe(in_array([$current, $next], $allowedTransitions, true));
        }
    }
});
