<?php

use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Platform\Plugin\Plugin;

class OrderProbePluginA extends Plugin
{
    public static array $calls = [];

    public function register(OpenKOSManager $platform): void
    {
        static::$calls[] = 'register:a';
    }

    public function boot(OpenKOSManager $platform): void
    {
        static::$calls[] = 'boot:a';
    }
}

class OrderProbePluginB extends Plugin
{
    public function register(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'register:b';
    }

    public function boot(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'boot:b';
    }
}

// Registries are singletons and ExamplePlugin registers by default,
// so assert "contains", not exact counts.
it('applies the example plugin registrations from config on boot', function () {
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
