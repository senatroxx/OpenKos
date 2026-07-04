<?php

use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Plugins\Example\ExamplePlugin;

// ExamplePlugin is disabled by default; enable it to prove convention-based
// route + migration loading from a plugin's own directory.
beforeEach(function () {
    config(['platform.plugins' => [ExamplePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();
});

it('loads routes shipped by an enabled plugin', function () {
    $this->getJson('/example')
        ->assertOk()
        ->assertJsonPath('plugin', 'openkos/example');
});

it('registers migration paths shipped by an enabled plugin', function () {
    $paths = app('migrator')->paths();

    expect(collect($paths)->contains(
        fn (string $p) => str_contains($p, 'Plugins/Example/database/migrations'),
    ))->toBeTrue();
});
