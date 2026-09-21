<?php

use App\Business\Dashboard\RentStatsCalculator;
use App\Data\Dashboard\RentLeaseData;
use Carbon\CarbonImmutable;

test('rent calculator classifies unpaid leases without database state', function (): void {
    $calculator = new RentStatsCalculator;

    $result = $calculator->computeStats([
        new RentLeaseData(1, 5, 'USD', '100.00', false, 'Tenant', 'unit', 'Unit 1', 'Property'),
        new RentLeaseData(2, 10, 'USD', '200.00', false, 'Tenant', 'unit', 'Unit 2', 'Property'),
        new RentLeaseData(3, 20, 'EUR', '300.00', true, 'Tenant', 'unit', 'Unit 3', 'Property'),
    ], [3], 10);

    expect($result)->toBe([
        'overdue' => ['count' => 1, 'amounts' => [['currency' => 'USD', 'amount' => '100.00']]],
        'due_today' => 1,
        'due_soon' => 0,
        'paid' => 1,
    ]);
});

test('rent calculator transforms an overdue lease using the supplied date', function (): void {
    $lease = new RentLeaseData(1, 5, 'USD', '100.00', false, 'Tenant', 'unit', 'Unit 1', 'Property');

    expect((new RentStatsCalculator)->transformEntry($lease, 10, CarbonImmutable::parse('2026-02-10')))
        ->toMatchArray(['rent_status' => 'overdue', 'days_overdue' => 5]);
});
