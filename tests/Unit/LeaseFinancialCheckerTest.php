<?php

use App\Business\Leases\LeaseFinancialChecker;

test('sums preloaded outstanding invoice amounts', function () {
    $result = (new LeaseFinancialChecker)->outstandingCheck(['12.345', '0.655']);

    expect($result)->toBe([
        'balance' => '13.000',
        'hasOutstanding' => true,
    ]);
});

test('reports no outstanding balance for an empty input', function () {
    expect((new LeaseFinancialChecker)->outstandingCheck([]))->toBe([
        'balance' => '0',
        'hasOutstanding' => false,
    ]);
});
