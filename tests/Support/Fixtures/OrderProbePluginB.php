<?php

namespace Tests\Support\Fixtures;

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class OrderProbePluginB extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/b', name: 'B', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'register:b';
    }

    public function boot(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'boot:b';
    }
}
