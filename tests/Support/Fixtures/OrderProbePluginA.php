<?php

namespace Tests\Support\Fixtures;

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class OrderProbePluginA extends Plugin
{
    public static array $calls = [];

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/a', name: 'A', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        static::$calls[] = 'register:a';
    }

    public function boot(OpenKOSManager $platform): void
    {
        static::$calls[] = 'boot:a';
    }
}
