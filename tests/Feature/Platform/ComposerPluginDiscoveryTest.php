<?php

use App\Services\Platform\ComposerPluginDiscovery;
use Composer\InstalledVersions;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\PlatformServiceProvider;
use Tests\Support\Fixtures\ComposerDiscoveryFixturePlugin;
use Tests\Support\Fixtures\ComposerDiscoverySecondFixturePlugin;

/**
 * @param  array<string, array<string, mixed>>  $packages
 */
function withComposerDiscoveryFixtures(array $packages, Closure $callback): mixed
{
    $original = require base_path('vendor/composer/installed.php');
    $originalDisabledPackages = config('platform.discovery.disabled_packages', []);
    $versions = [];
    $directories = [];

    try {
        foreach ($packages as $name => $metadata) {
            $directory = sys_get_temp_dir().'/openkos-plugin-'.str_replace('/', '-', $name).'-'.uniqid('', true);
            mkdir($directory, 0755, true);
            file_put_contents(
                $directory.'/composer.json',
                json_encode(['name' => $name, ...$metadata], JSON_THROW_ON_ERROR),
            );

            $directories[] = $directory;
            $versions[$name] = [
                'pretty_version' => '1.0.0',
                'version' => '1.0.0.0',
                'reference' => null,
                'type' => 'library',
                'install_path' => $directory,
                'aliases' => [],
                'dev_requirement' => false,
            ];
        }

        InstalledVersions::reload([
            'root' => [
                'name' => 'openkos/openkos',
                'pretty_version' => 'dev-test',
                'version' => 'dev-test',
                'reference' => null,
                'type' => 'project',
                'install_path' => base_path(),
                'aliases' => [],
                'dev' => true,
            ],
            'versions' => $versions,
        ]);

        return $callback();
    } finally {
        InstalledVersions::reload($original);
        config(['platform.discovery.disabled_packages' => $originalDisabledPackages]);

        foreach ($directories as $directory) {
            unlink($directory.'/composer.json');
            rmdir($directory);
        }
    }
}

it('discovers a plugin declared by Composer metadata', function () {
    $plugins = withComposerDiscoveryFixtures([
        'acme/composer-plugin' => [
            'extra' => [
                'openkos' => ['plugin' => ComposerDiscoveryFixturePlugin::class],
            ],
        ],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());

    expect($plugins)->toContain(ComposerDiscoveryFixturePlugin::class);
});

it('ignores packages without OpenKOS plugin metadata', function () {
    $plugins = withComposerDiscoveryFixtures([
        'acme/ordinary-package' => [],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());

    expect($plugins)->not->toContain(ComposerDiscoveryFixturePlugin::class);
});

it('skips disabled Composer packages', function () {
    config(['platform.discovery.disabled_packages' => ['acme/composer-plugin']]);

    $plugins = withComposerDiscoveryFixtures([
        'acme/composer-plugin' => [
            'extra' => [
                'openkos' => ['plugin' => ComposerDiscoveryFixturePlugin::class],
            ],
        ],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());

    expect($plugins)->not->toContain(ComposerDiscoveryFixturePlugin::class);
});

it('rejects malformed OpenKOS plugin metadata', function () {
    withComposerDiscoveryFixtures([
        'acme/malformed-plugin' => [
            'extra' => [
                'openkos' => ['plugin' => [ComposerDiscoveryFixturePlugin::class]],
            ],
        ],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());
})->throws(InvalidArgumentException::class, 'extra.openkos.plugin');

it('leaves plugin class validation to the platform bootstrap', function () {
    $plugins = withComposerDiscoveryFixtures([
        'acme/missing-plugin' => [
            'extra' => [
                'openkos' => ['plugin' => 'Acme\\MissingPlugin'],
            ],
        ],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());

    expect($plugins)->toContain('Acme\\MissingPlugin');
});

it('boots a Composer-discovered plugin through the normal lifecycle', function () {
    ComposerDiscoveryFixturePlugin::$registerCalls = 0;

    withComposerDiscoveryFixtures([
        'acme/composer-plugin' => [
            'extra' => [
                'openkos' => ['plugin' => ComposerDiscoveryFixturePlugin::class],
            ],
        ],
    ], function (): null {
        config(['platform.plugins' => []]);
        (new PlatformServiceProvider(app()))->boot();

        return null;
    });

    expect(ComposerDiscoveryFixturePlugin::$registerCalls)->toBe(1);
});

it('does not run any plugin lifecycle when discovery finds an invalid class', function () {
    ComposerDiscoveryFixturePlugin::$registerCalls = 0;
    app()->instance(PluginDiscovery::class, new class implements PluginDiscovery
    {
        public function discover(): array
        {
            return [ComposerDiscoveryFixturePlugin::class, 'Acme\\MissingPlugin'];
        }
    });

    config(['platform.plugins' => []]);

    expect(fn () => (new PlatformServiceProvider(app()))->boot())
        ->toThrow(InvalidArgumentException::class, 'Plugin class [Acme\\MissingPlugin] does not exist.');

    expect(ComposerDiscoveryFixturePlugin::$registerCalls)->toBe(0);
});

it('loads discovered plugins in deterministic package order', function () {
    $plugins = withComposerDiscoveryFixtures([
        'zeta/plugin' => [
            'extra' => [
                'openkos' => ['plugin' => ComposerDiscoveryFixturePlugin::class],
            ],
        ],
        'acme/plugin' => [
            'extra' => [
                'openkos' => ['plugin' => ComposerDiscoverySecondFixturePlugin::class],
            ],
        ],
    ], fn (): array => app(ComposerPluginDiscovery::class)->discover());

    expect($plugins)->toBe([
        ComposerDiscoverySecondFixturePlugin::class,
        ComposerDiscoveryFixturePlugin::class,
    ]);
});
