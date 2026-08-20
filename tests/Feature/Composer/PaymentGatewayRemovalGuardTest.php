<?php

use App\Composer\PaymentGatewayRemovalGuard;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use Tests\Support\Fixtures\PackageUninstallEvent;

it('blocks package removal while an active payment attempt remains', function () {
    PaymentAttempt::factory()->for(Invoice::factory())->create([
        'gateway_key' => 'xendit',
        'expires_at' => now()->addHour(),
    ]);

    expect(fn () => PaymentGatewayRemovalGuard::beforePackageUninstall(new PackageUninstallEvent([
        'openkos' => [
            'capabilities' => [
                'payment_gateways' => ['xendit'],
            ],
        ],
    ])))
        ->toThrow(RuntimeException::class, '1 active payment attempt(s) remain');
});

it('allows package removal when no active payment attempt remains', function () {
    PaymentAttempt::factory()->for(Invoice::factory())->create([
        'gateway_key' => 'xendit',
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn () => PaymentGatewayRemovalGuard::beforePackageUninstall(new PackageUninstallEvent([
        'openkos' => [
            'capabilities' => [
                'payment_gateways' => ['xendit'],
            ],
        ],
    ])))
        ->not->toThrow(Throwable::class);
});

it('ignores packages without payment gateway capabilities', function () {
    PaymentAttempt::factory()->for(Invoice::factory())->create([
        'gateway_key' => 'xendit',
        'expires_at' => now()->addHour(),
    ]);

    expect(fn () => PaymentGatewayRemovalGuard::beforePackageUninstall(new PackageUninstallEvent))
        ->not->toThrow(Throwable::class);
});
