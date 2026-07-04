<?php

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use App\Services\WhatsAppManager;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

beforeEach(function () {
    // 'log' (+ baileys/fonnte/whatsapp_cloud) are registered by WhatsAppPlugin
    // at boot from config; register an extra test driver into the same registry.
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

it('caches driver instances', function () {
    $first = $this->manager->driver('log');
    $second = $this->manager->driver('log');

    expect($first)->toBe($second);
});

it('throws for unknown driver', function () {
    expect(fn () => $this->manager->driver('unknown'))
        ->toThrow(InvalidArgumentException::class, 'not found');
});

it('send delegates to default driver', function () {
    config()->set('services.whatsapp.default', 'test_driver');
    $manager = app(WhatsAppManager::class);

    $driver = $manager->driver('test_driver');
    $manager->send('08123456789', 'Hello');

    expect($driver->sent)->toBe('08123456789');
});

it('health delegates to resolved driver', function () {
    $result = $this->manager->health();

    expect($result->healthy)->toBeTrue();
});

it('getPairingQrCode delegates to resolved driver', function () {
    $result = $this->manager->getPairingQrCode();

    expect($result)->toBeNull();
});

class TestWhatsAppDriver implements WhatsAppDriver
{
    public string $sent = '';

    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        $this->sent = $message->phone;
    }

    public function health(): DriverHealthResult
    {
        return new DriverHealthResult(true);
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void {}

    public function disconnect(): void {}
}
