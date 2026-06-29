<?php

use App\Contracts\WhatsAppDriver;
use App\Services\WhatsAppManager;

beforeEach(function () {
    config()->set('services.whatsapp', [
        'default' => 'log',
        'drivers' => [
            'log' => [
                'class' => \App\Notifications\Drivers\WhatsappLogDriver::class,
            ],
            'test_driver' => [
                'class' => TestWhatsAppDriver::class,
                'api_key' => 'env-default-key',
            ],
        ],
    ]);

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

class TestWhatsAppDriver implements WhatsAppDriver
{
    public string $sent = '';

    public function __construct(private array $config = []) {}

    public function send(\App\Data\WhatsApp\WhatsAppMessage $message): void
    {
        $this->sent = $message->phone;
    }

    public function health(): \App\Data\WhatsApp\DriverHealthResult
    {
        return new \App\Data\WhatsApp\DriverHealthResult(true);
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [];
    }
}
