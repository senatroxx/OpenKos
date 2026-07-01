<?php

use App\Data\WhatsApp\DriverHealthResult;

it('creates healthy result', function () {
    $result = new DriverHealthResult(true);

    expect($result->healthy)->toBeTrue();
    expect($result->message)->toBeNull();
});

it('creates failed result with message', function () {
    $result = new DriverHealthResult(false, 'Connection failed');

    expect($result->healthy)->toBeFalse();
    expect($result->message)->toBe('Connection failed');
});

it('properties are readonly', function () {
    $result = new DriverHealthResult(true);

    expect(fn () => $result->healthy = false)->toThrow(Error::class);
});
