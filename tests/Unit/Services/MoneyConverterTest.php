<?php

use App\Services\Payments\MoneyConverter;

it('converts major units using the configured currency scale', function () {
    $converter = new MoneyConverter;

    expect($converter->toMoney('1500000.00', 'idr')->minorUnits)->toBe(1_500_000)
        ->and($converter->toMoney('15.50', 'usd')->minorUnits)->toBe(1_550)
        ->and($converter->toMoney('15.50', 'ron')->minorUnits)->toBe(1_550)
        ->and($converter->toMoney('1500', 'jpy')->minorUnits)->toBe(1_500);
});

it('rejects unsupported currency precision and unknown currencies', function () {
    $converter = new MoneyConverter;

    expect(fn () => $converter->toMoney('15.501', 'USD'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $converter->toMoney('15.00', 'ZZZ'))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes the canonical scale map for validation and presentation', function () {
    $converter = new MoneyConverter;

    expect($converter->scales()['IDR'])->toBe(0)
        ->and($converter->scales()['KWD'])->toBe(3)
        ->and($converter->normalizeAmount('1.234', 'KWD'))->toBe('1.234');

    expect(fn () => $converter->normalizeAmount('1.2341', 'KWD'))
        ->toThrow(InvalidArgumentException::class);
});
