<?php

use OpenKOS\Plugins\WhatsApp\WhatsAppPlugin;

// Reference plugin, disabled by default (see below):
// use OpenKOS\Plugins\Example\ExamplePlugin;

return [

    /*
    |--------------------------------------------------------------------------
    | Platform version
    |--------------------------------------------------------------------------
    |
    | Plugins declare a `coreVersion` constraint in their manifest; it is
    | checked against this value at boot (see PluginLoader).
    |
    */

    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Plugin classes booted by the PlatformServiceProvider. Each must extend
    | OpenKOS\Platform\Plugin\Plugin. Composer-based discovery may replace
    | this list later (see OpenKOS\Core\Contracts\PluginDiscovery).
    |
    */

    'plugins' => [
        // Core: registers the built-in WhatsApp drivers into NotificationRegistry.
        WhatsAppPlugin::class,

        // Reference plugin (src/Plugins/Example) — a live demo of every consumed
        // registry: a sidebar item, a Dashboard sub-page, a settings page, and a
        // workspace-header badge. Disabled so it stays out of the UI. To see it,
        // uncomment the line below, its `use` import above, AND the `./example`
        // import in resources/js/plugins/index.ts.
        // ExamplePlugin::class,
    ],

];
