<?php

use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Plugins\Example\ExamplePlugin;
use Tests\Support\Fixtures\OrderProbePluginA;
use Tests\Support\Fixtures\OrderProbePluginB;

// ExamplePlugin is disabled by default, so enable it explicitly to prove the
// registration path. Registries are singletons, so assert "contains".
it('applies a plugins registrations across every registry on boot', function () {
    config(['platform.plugins' => [ExamplePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();

    $navTitles = array_map(fn ($item) => $item->title, OpenKOS::navigation()->items('main'));

    expect($navTitles)->toContain('Example Plugin')
        ->and(OpenKOS::settings()->pages())->toHaveKey('example')
        ->and(OpenKOS::dashboard()->pages())->toHaveKey('example');
});

it('runs every register() before any boot()', function () {
    OrderProbePluginA::$calls = [];
    config(['platform.plugins' => [OrderProbePluginA::class, OrderProbePluginB::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(OrderProbePluginA::$calls)->toBe(['register:a', 'register:b', 'boot:a', 'boot:b']);
});
