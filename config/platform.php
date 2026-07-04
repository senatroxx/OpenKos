<?php

use OpenKOS\Plugins\Example\ExamplePlugin;
use OpenKOS\Plugins\WhatsApp\WhatsAppPlugin;

return [

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

    'plugins' => array_values(array_filter([
        // Core: registers the built-in WhatsApp drivers into NotificationRegistry.
        WhatsAppPlugin::class,
        // Demo — set OPENKOS_EXAMPLE_PLUGIN=false to hide (e.g. production).
        env('OPENKOS_EXAMPLE_PLUGIN', true) ? ExamplePlugin::class : null,
    ])),

];
