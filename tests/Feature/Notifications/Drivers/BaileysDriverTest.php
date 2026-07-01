<?php

use App\Data\WhatsApp\WhatsAppMessage;
use App\Notifications\Drivers\BaileysDriver;
use Illuminate\Support\Facades\Http;

function fakeResponse(array $data = [], int $status = 200, bool $success = true, string $message = '')
{
    return Http::response([
        'meta' => ['success' => $success, 'code' => $status, 'status' => $success ? 'success' : 'error', 'message' => $message],
        'data' => $data,
        'errors' => null,
    ], $status);
}

it('sends message via Baileys service', function () {
    Http::fake([
        '*/api/send' => fakeResponse(),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);

    $driver->send(new WhatsAppMessage('08123456789', 'Test message'));

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'http://localhost:3000/api/send'
            && $request->method() === 'POST'
            && ($body['to'] ?? null) === '628123456789'
            && ($body['text'] ?? null) === 'Test message';
    });
});

it('includes HMAC signature headers', function () {
    Http::fake([
        '*/api/send' => fakeResponse(),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'test-secret']);
    $driver->send(new WhatsAppMessage('08123456789', 'Test'));

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Timestamp')
            && $request->hasHeader('X-Signature')
            && $request->header('X-Key-Id') === [];
    });
});

it('normalizes phone number before sending', function () {
    Http::fake([
        '*/api/send' => fakeResponse(),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);

    $driver->send(new WhatsAppMessage('+62 812-3456-7890', 'Test'));

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return ($body['to'] ?? null) === '6281234567890';
    });
});

it('throws on send failure', function () {
    Http::fake([
        '*/api/send' => fakeResponse([], 200, false, 'Invalid target number'),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);

    expect(fn () => $driver->send(new WhatsAppMessage('08123456789', 'Test')))
        ->toThrow(RuntimeException::class, 'Invalid target number');
});

it('throws when url is not configured', function () {
    $driver = new BaileysDriver([]);

    expect(fn () => $driver->send(new WhatsAppMessage('08123456789', 'Test')))
        ->toThrow(RuntimeException::class, 'Baileys URL is not configured');
});

it('returns healthy when connected', function () {
    Http::fake([
        '*/api/sessions' => fakeResponse([
            'state' => 'connected',
            'phone' => '628123456789',
            'lastConnected' => '2026-06-30T12:00:00.000Z',
        ]),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $result = $driver->health();

    expect($result->healthy)->toBeTrue();
    expect($result->phone)->toBe('628123456789');
    expect($result->lastConnected)->toBe('2026-06-30T12:00:00.000Z');
});

it('returns unhealthy when disconnected', function () {
    Http::fake([
        '*/api/sessions' => fakeResponse([
            'state' => 'disconnected',
            'phone' => null,
            'lastConnected' => null,
        ]),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
    expect($result->phone)->toBeNull();
});

it('returns unhealthy on request error', function () {
    Http::fake([
        '*/api/sessions' => Http::response(null, 500),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $result = $driver->health();

    expect($result->healthy)->toBeFalse();
});

it('returns qr code', function () {
    Http::fake([
        '*/api/sessions/qr' => fakeResponse(['qr' => 'data:image/png;base64,abc123']),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $qr = $driver->getPairingQrCode();

    expect($qr)->toBe('abc123');
});

it('returns null qr when not available', function () {
    Http::fake([
        '*/api/sessions/qr' => fakeResponse([], 400, false, 'No QR available'),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $qr = $driver->getPairingQrCode();

    expect($qr)->toBeNull();
});

it('initiates pairing via POST /api/sessions', function () {
    Http::fake([
        '*/api/sessions' => fakeResponse(),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $driver->pair();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:3000/api/sessions'
            && $request->method() === 'POST';
    });
});

it('throws on pair failure', function () {
    Http::fake([
        '*/api/sessions' => fakeResponse([], 400, false, 'Session error'),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);

    expect(fn () => $driver->pair())->toThrow(RuntimeException::class, 'Session error');
});

it('disconnects via DELETE /api/sessions', function () {
    Http::fake([
        '*/api/sessions' => fakeResponse(),
    ]);

    $driver = new BaileysDriver(['url' => 'http://localhost:3000', 'api_key' => 'secret']);
    $driver->disconnect();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:3000/api/sessions'
            && $request->method() === 'DELETE';
    });
});

it('supports pairing', function () {
    $driver = new BaileysDriver;
    expect($driver->supportsPairing())->toBeTrue();
});

it('has updated url and api_key config schema', function () {
    $driver = new BaileysDriver;

    $schema = $driver->configurationSchema();

    expect($schema)->toHaveKeys(['url', 'api_key']);
    expect($schema['url']['label'])->toBe('Service URL');
    expect($schema['url']['placeholder'])->toBe('http://localhost:3000');
    expect($schema['url']['required'])->toBeTrue();
    expect($schema['api_key']['required'])->toBeTrue();
});
