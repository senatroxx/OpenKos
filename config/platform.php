<?php

use OpenKOS\Plugins\Example\ExamplePlugin;

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
        // Now visible in the UI — set OPENKOS_EXAMPLE_PLUGIN=false to hide (e.g. production).
        env('OPENKOS_EXAMPLE_PLUGIN', true) ? ExamplePlugin::class : null,
    ])),

];
