<?php

use App\Exceptions\WhatsAppDriverNotFoundException;
use App\Services\WhatsAppManager;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;
use Tests\Support\Fakes\TestWhatsAppDriver;

beforeEach(function () {
    // Bundled drivers are registered by WhatsAppPlugin at boot from config;
    // register an extra test driver into the same registry.
    app(NotificationRegistry::class)->registerDriver(new NotificationDriverRegistration(
        name: 'test_driver',
        channel: 'whatsapp',
        driverClass: TestWhatsAppDriver::class,
        label: 'Test',
        config: ['api_key' => 'env-default-key'],
    ));

    config()->set('services.whatsapp.default', 'log');

    $this->manager = app(WhatsAppManager::class);
});

it('resolves default driver', function () {
    $driver = $this->manager->driver();

    expect($driver)->toBeInstanceOf(WhatsAppDriver::class);
});

it('resolves driver by name', function () {
    $driver = $this->manager->driver('log');

    expect($driver)->toBeInstanceOf(WhatsAppDriver::class);
});

it('resolves driver instances via container', function () {
    $first = $this->manager->driver('log');
    $second = $this->manager->driver('log');

    expect($first)->toBeInstanceOf(WhatsAppDriver::class);
    expect($second)->toBeInstanceOf(WhatsAppDriver::class);
});

it('throws for unknown driver', function () {
    expect(fn () => $this->manager->driver('unknown'))
        ->toThrow(WhatsAppDriverNotFoundException::class);
});

it('send delegates to default driver', function () {
    config()->set('services.whatsapp.default', 'test_driver');
    TestWhatsAppDriver::$sentMessages = [];

    $manager = app(WhatsAppManager::class);
    $manager->send('08123456789', 'Hello');

    expect(TestWhatsAppDriver::$sentMessages)->toContain('08123456789');
});

it('health delegates to resolved driver', function () {
    $result = $this->manager->health();

    expect($result->healthy)->toBeTrue();
});

it('getPairingQrCode delegates to resolved driver', function () {
    $result = $this->manager->getPairingQrCode();

    expect($result)->toBeNull();
});
