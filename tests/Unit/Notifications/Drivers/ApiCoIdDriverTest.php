<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\ApiCoIdDriver;

it('throws on send', function () {
    $driver = new ApiCoIdDriver;

    expect(fn () => $driver->send(new WhatsAppMessage('08123456789', 'Test')))
        ->toThrow(RuntimeException::class, 'not implemented');
});

it('returns unhealthy', function () {
    $driver = new ApiCoIdDriver;

    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
});

it('does not support pairing', function () {
    $driver = new ApiCoIdDriver;

    expect($driver->supportsPairing())->toBeFalse();
});

it('has api_key and sender_name config schema', function () {
    $driver = new ApiCoIdDriver;

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKeys(['api_key', 'sender_name']);
    expect($schema['api_key']['required'])->toBeTrue();
    expect($schema['sender_name']['required'])->toBeFalse();
});
