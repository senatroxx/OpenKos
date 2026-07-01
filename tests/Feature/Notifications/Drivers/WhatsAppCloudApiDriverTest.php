<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\WhatsAppCloudApiDriver;
use Illuminate\Support\Facades\Http;

it('sends message via WhatsApp Cloud API', function () {
    Http::fake([
        'graph.facebook.com/v22.0/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '+628123456789', 'wa_id' => '628123456789']],
            'messages' => [['id' => 'wamid.abc123']],
        ]),
    ]);

    $driver = new WhatsAppCloudApiDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'valid-token',
    ]);

    $driver->send(new WhatsAppMessage('+628123456789', 'Hello'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v22.0/123456789/messages'
            && $request->method() === 'POST'
            && ($body['messaging_product'] ?? null) === 'whatsapp'
            && ($body['to'] ?? null) === '+628123456789'
            && ($body['type'] ?? null) === 'text'
            && ($body['text']['body'] ?? null) === 'Hello';
    });
});

it('throws on Graph API error', function () {
    Http::fake([
        'graph.facebook.com/v22.0/*' => Http::response([
            'error' => ['message' => 'Invalid token', 'code' => 401],
        ], 401),
    ]);

    $driver = new WhatsAppCloudApiDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'bad-token',
    ]);

    expect(fn () => $driver->send(new WhatsAppMessage('+628123456789', 'Hello')))
        ->toThrow(RuntimeException::class, 'Invalid token');
});

it('throws when phone_number_id missing', function () {
    $driver = new WhatsAppCloudApiDriver(['access_token' => 'token']);

    expect(fn () => $driver->send(new WhatsAppMessage('+628123456789', 'Hello')))
        ->toThrow(RuntimeException::class, 'phone_number_id is not configured');
});

it('throws when access_token missing', function () {
    $driver = new WhatsAppCloudApiDriver(['phone_number_id' => '123']);

    expect(fn () => $driver->send(new WhatsAppMessage('+628123456789', 'Hello')))
        ->toThrow(RuntimeException::class, 'access_token is not configured');
});

it('returns healthy when credentials valid', function () {
    Http::fake([
        'graph.facebook.com/v22.0/123456789' => Http::response([
            'verified_name' => 'Test Business',
            'display_phone_number' => '+628123456789',
            'id' => '123456789',
        ]),
    ]);

    $driver = new WhatsAppCloudApiDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'valid-token',
    ]);

    $result = $driver->health();

    expect($result->healthy)->toBeTrue();
    expect($result->phone)->toBe('+628123456789');
});

it('returns unhealthy when token invalid', function () {
    Http::fake([
        'graph.facebook.com/v22.0/123456789' => Http::response([
            'error' => ['message' => 'Invalid OAuth token', 'code' => 401],
        ], 401),
    ]);

    $driver = new WhatsAppCloudApiDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'bad-token',
    ]);

    expect($driver->health()->healthy)->toBeFalse();
});

it('returns unhealthy when credentials missing', function () {
    $driver = new WhatsAppCloudApiDriver([]);

    expect($driver->health()->healthy)->toBeFalse();
});

it('does not support pairing', function () {
    $driver = new WhatsAppCloudApiDriver([]);

    expect($driver->supportsPairing())->toBeFalse();
});

it('returns null for qr code', function () {
    $driver = new WhatsAppCloudApiDriver([]);

    expect($driver->getPairingQrCode())->toBeNull();
});

it('has phone_number_id and access_token config schema', function () {
    $driver = new WhatsAppCloudApiDriver([]);

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKeys(['phone_number_id', 'access_token']);
    expect($schema['phone_number_id']['required'])->toBeTrue();
    expect($schema['phone_number_id']['type'])->toBe('text');
    expect($schema['access_token']['required'])->toBeTrue();
    expect($schema['access_token']['type'])->toBe('password');
});

it('normalizes phone to E.164 with plus prefix', function () {
    Http::fake([
        'graph.facebook.com/v22.0/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '+628123456789', 'wa_id' => '628123456789']],
            'messages' => [['id' => 'wamid.abc']],
        ]),
    ]);

    $driver = new WhatsAppCloudApiDriver([
        'phone_number_id' => '123456789',
        'access_token' => 'valid-token',
    ]);

    $driver->send(new WhatsAppMessage('+62 812-3456-7890', 'Test'));

    Http::assertSent(function ($request) {
        return $request->data()['to'] === '+6281234567890';
    });
});
