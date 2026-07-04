<?php

use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

function reg(string $name, string $channel = 'whatsapp'): NotificationDriverRegistration
{
    return new NotificationDriverRegistration(
        name: $name,
        channel: $channel,
        driverClass: 'App\\Notifications\\Drivers\\WhatsappLogDriver',
        label: ucfirst($name),
        config: ['token' => 'secret'],
    );
}

it('registers and looks up drivers', function () {
    $registry = new NotificationRegistry;
    $log = reg('log');

    $registry->registerDriver($log);

    expect($registry->get('log'))->toBe($log)
        ->and($registry->has('log'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->get('missing'))->toBeNull();
});

it('filters drivers by channel', function () {
    $registry = new NotificationRegistry;
    $registry->registerDriver(reg('log', 'whatsapp'))
        ->registerDriver(reg('smtp', 'email'));

    expect($registry->forChannel('whatsapp'))->toHaveCount(1)
        ->and($registry->forChannel('whatsapp')[0]->name)->toBe('log')
        ->and($registry->forChannel('sms'))->toBe([]);
});

it('serializes without leaking credentials or class', function () {
    $registry = new NotificationRegistry;
    $registry->registerDriver(reg('log'));

    expect($registry->toArray())->toBe([
        ['name' => 'log', 'channel' => 'whatsapp', 'label' => 'Log'],
    ]);
});

it('throws on duplicate driver names', function () {
    $registry = new NotificationRegistry;
    $registry->registerDriver(reg('log'));

    $registry->registerDriver(reg('log'));
})->throws(InvalidArgumentException::class, 'Notification driver [log] is already registered.');
