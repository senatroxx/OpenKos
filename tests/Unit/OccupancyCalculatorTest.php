<?php

use App\Business\Leases\OccupancyCalculator;

test('a unit can accommodate occupants within capacity', function () {
    expect((new OccupancyCalculator)->canAccommodate(3, 2, 1))->toBeTrue();
});

test('a unit rejects occupants beyond capacity', function () {
    expect((new OccupancyCalculator)->canAccommodate(3, 2, 2))->toBeFalse();
});
