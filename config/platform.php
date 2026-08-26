<?php

use Composer\InstalledVersions;
use OpenKOS\Plugins\Mail\MailPlugin;
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

    'version' => InstalledVersions::getPrettyVersion('openkos/platform') ?: '0.2.0',

    'discovery' => [
        // External plugin discovery is host policy and is enabled explicitly here.
        'enabled' => true,
        'disabled_packages' => [],
    ],

    'runtime' => [
        'enabled' => true,
        'path' => env('OPENKOS_PLUGIN_PATH', storage_path('app/private/plugins')),
        'max_upload_bytes' => 64 * 1024 * 1024,
        'max_files' => 5000,
        'max_uncompressed_bytes' => 268_435_456,
        'shared_packages' => [
            'composer/semver',
            'laravel/framework',
            'openkos/platform',
        ],
        'shared_package_prefixes' => [
            'illuminate/',
            'laravel/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Explicit plugin classes are merged with trusted Composer-discovered
    | plugins when discovery is enabled. Each must extend
    | OpenKOS\Platform\Plugin\Plugin.
    |
    */

    'plugins' => [
        // Core: registers the built-in Mail and WhatsApp drivers into NotificationRegistry.
        MailPlugin::class,
        WhatsAppPlugin::class,

        // Reference plugin (src/Plugins/Example) — a live demo of every consumed
        // registry: a sidebar item, a Dashboard sub-page, a settings page, and a
        // workspace-header badge. Disabled so it stays out of the UI. To see it,
        // uncomment the line below, its `use` import above, AND the `./example`
        // import in resources/js/plugins/index.ts.
        // ExamplePlugin::class,
    ],

];
