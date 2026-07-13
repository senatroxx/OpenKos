<?php

namespace OpenKOS\Platform\Plugin;

use OpenKOS\Platform\Installation\InstallationStepRegistry;
use OpenKOS\Platform\OpenKOSManager;

abstract class Plugin
{
    /**
     * Identity, version, and dependency metadata for this plugin.
     */
    abstract public function manifest(): PluginManifest;

    /**
     * Register extensions on the platform. Called for every plugin
     * (in dependency order) before any plugin's boot() runs.
     */
    abstract public function register(OpenKOSManager $platform): void;

    /**
     * Optional hook that runs after all plugins have registered.
     */
    public function boot(OpenKOSManager $platform): void {}

    /**
     * Register optional installation steps contributed by this plugin.
     */
    public function installationSteps(InstallationStepRegistry $registry): void {}

    /**
     * Domain-event subscriptions, wired via Laravel's event dispatcher.
     *
     * @return array<class-string, class-string|callable|array<class-string|callable>>
     *                                                                                 event class => listener(s)
     */
    public function listens(): array
    {
        return [];
    }
}
