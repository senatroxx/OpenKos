<?php

use Composer\InstalledVersions;
use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Plugins\Example\ExamplePlugin;
use Tests\Support\Fixtures\OrderProbePluginA;
use Tests\Support\Fixtures\OrderProbePluginB;

// ExamplePlugin is disabled by default, so enable it explicitly to prove the
// registration path.
it('applies a plugins registrations across every registry on boot', function () {
    config(['platform.plugins' => [ExamplePlugin::class]]);
    $this->bootPlatformWithIsolatedRegistries();

    $navTitles = array_map(fn ($item) => $item->title, OpenKOS::navigation()->items('main'));

    expect($navTitles)->toContain('Example Plugin')
        ->and(OpenKOS::settings()->pages())->toHaveKey('example')
        ->and(OpenKOS::dashboard()->pages())->toHaveKey('example');
});

it('runs every register() before any boot()', function () {
    OrderProbePluginA::$calls = [];
    config(['platform.plugins' => [OrderProbePluginA::class, OrderProbePluginB::class]]);

    $this->bootPlatformWithIsolatedRegistries();

    expect(OrderProbePluginA::$calls)->toBe(['register:a', 'register:b', 'boot:a', 'boot:b']);
});

it('registers an installed Xendit plugin once during application boot', function () {
    if (! in_array('openkos/payment-xendit', InstalledVersions::getInstalledPackages(), true)) {
        $this->markTestSkipped('The openkos/payment-xendit package is not installed.');
    }

    expect(app(PaymentRegistry::class)->gateways())->toHaveKey('xendit');
});
