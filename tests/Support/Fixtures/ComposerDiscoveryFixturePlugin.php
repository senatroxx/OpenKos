<?php

namespace Tests\Support\Fixtures;

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class ComposerDiscoveryFixturePlugin extends Plugin
{
    public static int $registerCalls = 0;

    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'fixture/composer-plugin',
            name: 'Composer Fixture',
            version: '1.0.0',
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        self::$registerCalls++;
    }
}
