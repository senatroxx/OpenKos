<?php

namespace Tests\Support\Fixtures;

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class ComposerDiscoverySecondFixturePlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'fixture/composer-plugin-second',
            name: 'Composer Second Fixture',
            version: '1.0.0',
        );
    }

    public function register(OpenKOSManager $platform): void {}
}
