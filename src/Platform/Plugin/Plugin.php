<?php

namespace OpenKOS\Platform\Plugin;

use OpenKOS\Platform\OpenKOSManager;

abstract class Plugin
{
    /**
     * Register extensions on the platform. Called for every plugin
     * before any plugin's boot() runs.
     */
    abstract public function register(OpenKOSManager $platform): void;

    /**
     * Optional hook that runs after all plugins have registered.
     */
    public function boot(OpenKOSManager $platform): void {}
}
