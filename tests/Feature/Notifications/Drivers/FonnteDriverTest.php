<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\FonnteDriver;
use Illuminate\Support\Facades\Http;

it('sends message via fonnte api', function () {
    Http::fake([
        'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['12345'], 'requestid' => 1]),
    ]);

    $driver = new FonnteDriver(['token' => 'valid-token']);
    $driver->send(new WhatsAppMessage('+628123456789', 'Hello'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request->method() === 'POST';
    });
});

it('throws on failed send', function () {
    Http::fake([
        'api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'insufficient quota']),
    ]);

    $driver = new FonnteDriver(['token' => 'valid-token']);

    expect(fn () => $driver->send(new WhatsAppMessage('+628123456789', 'Hello')))
        ->toThrow(RuntimeException::class, 'insufficient quota');
});

it('throws when token missing on send', function () {
    $driver = new FonnteDriver;

    expect(fn () => $driver->send(new WhatsAppMessage('+628123456789', 'Hello')))
        ->toThrow(RuntimeException::class, 'token is not configured');
});

it('returns healthy when device is connected', function () {
    Http::fake([
        'api.fonnte.com/device' => Http::response([
            'status' => true,
            'device_status' => 'connect',
            'device' => '628123456789',
        ]),
    ]);

    $driver = new FonnteDriver(['token' => 'valid-token']);

    expect($driver->health()->healthy)->toBeTrue();
});

it('returns unhealthy when device is disconnected', function () {
    Http::fake([
        'api.fonnte.com/device' => Http::response([
            'status' => true,
            'device_status' => 'disconnect',
        ]),
    ]);

    $driver = new FonnteDriver(['token' => 'valid-token']);

    expect($driver->health()->healthy)->toBeFalse();
});

it('returns unhealthy when token invalid', function () {
    Http::fake([
        'api.fonnte.com/device' => Http::response([
            'status' => false,
            'reason' => 'token invalid',
        ]),
    ]);

    $driver = new FonnteDriver(['token' => 'bad-token']);

    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
    expect($result->message)->toBe('token invalid');
});

it('returns unhealthy when token missing on health', function () {
    $driver = new FonnteDriver;

    expect($driver->health()->healthy)->toBeFalse();
    expect($driver->health()->message)->toBe('Fonnte token is not configured.');
});

it('does not support pairing', function () {
    $driver = new FonnteDriver(['token' => 'valid-token']);

    expect($driver->supportsPairing())->toBeFalse();
});

it('has token config schema', function () {
    $driver = new FonnteDriver;

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKey('token');
    expect($schema['token']['required'])->toBeTrue();
});

it('returns null for qr code', function () {
    $driver = new FonnteDriver(['token' => 'valid-token']);

    expect($driver->getPairingQrCode())->toBeNull();
});
