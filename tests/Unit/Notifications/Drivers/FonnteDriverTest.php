<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\FonnteDriver;

it('throws on send', function () {
    $driver = new FonnteDriver;

    expect(fn () => $driver->send(new WhatsAppMessage('08123456789', 'Test')))
        ->toThrow(RuntimeException::class, 'not implemented');
});

it('returns unhealthy', function () {
    $driver = new FonnteDriver;

    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
});

it('does not support pairing', function () {
    $driver = new FonnteDriver;

    expect($driver->supportsPairing())->toBeFalse();
});

it('has token config schema', function () {
    $driver = new FonnteDriver;

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKey('token');
    expect($schema['token']['required'])->toBeTrue();
});
