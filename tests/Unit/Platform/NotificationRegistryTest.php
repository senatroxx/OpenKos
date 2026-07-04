<?php

use OpenKOS\Core\Contracts\NotificationDriver;
use OpenKOS\Platform\Notification\NotificationRegistry;

function fakeNotificationDriver(): NotificationDriver
{
    return new class implements NotificationDriver
    {
        public function name(): string
        {
            return 'fake';
        }

        public function channel(): string
        {
            return 'whatsapp';
        }

        public function send(string $recipient, string $message, array $options = []): void {}

        public function configurationSchema(): array
        {
            return [];
        }
    };
}

it('registers drivers as class-strings or instances', function () {
    $registry = new NotificationRegistry;
    $instance = fakeNotificationDriver();

    $registry->registerDriver('by-class', $instance::class)
        ->registerDriver('by-instance', $instance);

    expect($registry->has('by-class'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->drivers())->toBe(['by-class' => $instance::class, 'by-instance' => $instance]);
});

it('serializes to driver names only', function () {
    $registry = new NotificationRegistry;
    $registry->registerDriver('fake', fakeNotificationDriver());

    expect($registry->toArray())->toBe(['fake']);
});

it('throws on duplicate driver names', function () {
    $registry = new NotificationRegistry;
    $registry->registerDriver('fake', fakeNotificationDriver());

    $registry->registerDriver('fake', fakeNotificationDriver());
})->throws(InvalidArgumentException::class, 'Notification driver [fake] is already registered.');
