<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\WhatsappLogDriver;
use Illuminate\Support\Facades\Log;

it('sends without exception', function () {
    $driver = new WhatsappLogDriver;

    $driver->send(new WhatsAppMessage('08123456789', 'Test message'));

    expect(true)->toBeTrue();
});

it('returns healthy', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->health()->healthy)->toBeTrue();
});

it('does not support pairing', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->supportsPairing())->toBeFalse();
});

it('has empty configuration schema', function () {
    $driver = new WhatsappLogDriver;

    expect($driver->configurationSchema())->toBe([]);
});
