<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\BaileysDriver;

it('throws on send', function () {
    $driver = new BaileysDriver;

    expect(fn () => $driver->send(new WhatsAppMessage('08123456789', 'Test')))
        ->toThrow(RuntimeException::class, 'not implemented');
});

it('returns unhealthy', function () {
    $driver = new BaileysDriver;

    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
});

it('supports pairing', function () {
    $driver = new BaileysDriver;

    expect($driver->supportsPairing())->toBeTrue();
});

it('has url and api_key config schema', function () {
    $driver = new BaileysDriver;

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKeys(['url', 'api_key']);
    expect($schema['url']['required'])->toBeTrue();
    expect($schema['api_key']['required'])->toBeFalse();
});
