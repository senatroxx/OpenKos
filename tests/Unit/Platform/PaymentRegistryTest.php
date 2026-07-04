<?php

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Platform\Payment\PaymentRegistry;

function fakePaymentGateway(): PaymentGateway
{
    return new class implements PaymentGateway
    {
        public function key(): string
        {
            return 'fake';
        }

        public function displayName(): string
        {
            return 'Fake Gateway';
        }

        public function createPayment(array $payload): array
        {
            return [];
        }

        public function handleCallback(array $payload): array
        {
            return [];
        }

        public function configurationSchema(): array
        {
            return [];
        }
    };
}

it('registers gateways as class-strings or instances', function () {
    $registry = new PaymentRegistry;
    $instance = fakePaymentGateway();

    $registry->registerGateway('by-class', $instance::class)
        ->registerGateway('by-instance', $instance);

    expect($registry->has('by-class'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse()
        ->and($registry->gateways())->toBe(['by-class' => $instance::class, 'by-instance' => $instance]);
});

it('serializes to gateway keys only', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('fake', fakePaymentGateway());

    expect($registry->toArray())->toBe(['fake']);
});

it('throws on duplicate gateway keys', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('fake', fakePaymentGateway());

    $registry->registerGateway('fake', fakePaymentGateway());
})->throws(InvalidArgumentException::class, 'Payment gateway [fake] is already registered.');
