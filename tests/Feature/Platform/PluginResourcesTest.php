<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Plugins\Example\ExamplePlugin;
use Spatie\Permission\Models\Permission;

// ExamplePlugin is disabled by default; enable it to prove convention-based
// route + migration loading and plugin permission registration.
beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['platform.plugins' => [ExamplePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();
});

it('loads routes shipped by an enabled plugin', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->getJson('/example')
        ->assertOk()
        ->assertJsonPath('plugin', 'openkos/example');
});

it('registers migration paths shipped by an enabled plugin', function () {
    $paths = app('migrator')->paths();

    expect(collect($paths)->contains(
        fn (string $p) => str_contains($p, 'Plugins/Example/database/migrations'),
    ))->toBeTrue();
});

it('persists plugin-declared permissions on sync', function () {
    expect(Permission::where('name', 'example.view')->exists())->toBeFalse();

    $this->artisan('platform:permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'example.view')->exists())->toBeTrue();
});
