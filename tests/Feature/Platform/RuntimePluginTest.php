<?php

use Illuminate\Support\Facades\File;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Permission\PermissionRegistry;

beforeEach(function (): void {
    $this->runtimePluginPath = sys_get_temp_dir().'/openkos-runtime-'.bin2hex(random_bytes(8));
    $this->originalRuntimeConfig = [
        'platform.plugins' => config('platform.plugins'),
        'platform.runtime.path' => config('platform.runtime.path'),
        'platform.runtime.max_files' => config('platform.runtime.max_files'),
        'platform.runtime.max_uncompressed_bytes' => config('platform.runtime.max_uncompressed_bytes'),
    ];
    config([
        'platform.plugins' => [],
        'platform.runtime.path' => $this->runtimePluginPath,
    ]);
    app()->forgetInstance(PluginDiscovery::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->runtimePluginPath);
    config($this->originalRuntimeConfig);
});

it('installs and boots a valid runtime plugin without changing root Composer files', function (): void {
    $artifact = makeRuntimePluginArtifact();
    $composerBefore = file_get_contents(base_path('composer.json'));
    $lockBefore = file_get_contents(base_path('composer.lock'));

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])
        ->assertSuccessful();

    expect(file_get_contents(base_path('composer.json')))->toBe($composerBefore)
        ->and(file_get_contents(base_path('composer.lock')))->toBe($lockBefore)
        ->and(is_file($this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json'))->toBeTrue()
        ->and(json_decode(file_get_contents($this->runtimePluginPath.'/state.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toMatchArray([$artifact['id'] => ['enabled' => true]]);

    $this->bootPlatformWithIsolatedRegistries();

    expect(app(PermissionRegistry::class)->all())->toHaveKey('runtime-fixture.view');
});

it('does not boot a disabled runtime plugin', function (): void {
    $artifact = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    $this->artisan('plugin:disable', ['id' => $artifact['id']])->assertSuccessful();

    $this->bootPlatformWithIsolatedRegistries();

    expect(app(PermissionRegistry::class)->all())->not->toHaveKey('runtime-fixture.view');
});

it('revalidates an installed plugin before enabling it', function (): void {
    $artifact = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    $this->artisan('plugin:disable', ['id' => $artifact['id']])->assertSuccessful();
    File::deleteDirectory($this->runtimePluginPath.'/'.$artifact['id'].'/vendor');

    $this->artisan('plugin:enable', ['id' => $artifact['id']])
        ->assertFailed();

    expect(json_decode(file_get_contents($this->runtimePluginPath.'/state.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toMatchArray([$artifact['id'] => ['enabled' => false]]);
});

it('rejects unsafe ZIP paths before extraction', function (): void {
    $zip = makeZip(['../outside.txt' => 'unsafe']);

    $this->artisan('plugin:install', ['zip' => $zip])
        ->assertFailed();

    expect(count(glob($this->runtimePluginPath.'/.staging/*') ?: []))->toBe(0)
        ->and(count(glob($this->runtimePluginPath.'/*/*') ?: []))->toBe(0);
});

it('rejects artifacts that exceed the configured archive limit', function (): void {
    config(['platform.runtime.max_uncompressed_bytes' => 3]);
    $zip = makeZip(['manifest.json' => '12345']);

    $this->artisan('plugin:install', ['zip' => $zip])
        ->assertFailed();
});

it('reports an enabled package with a malformed manifest without booting it', function (): void {
    $artifact = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    file_put_contents($this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json', '{broken');

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Invalid (enabled)');
});

it('rejects a runtime plugin that conflicts with an explicit plugin', function (): void {
    $artifact = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    config(['platform.plugins' => [$artifact['class']]]);

    expect(fn (): mixed => $this->bootPlatformWithIsolatedRegistries())
        ->toThrow(RuntimeException::class, 'conflicts');
});

it('fails on corrupted runtime state instead of treating it as disabled', function (): void {
    mkdir($this->runtimePluginPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');

    $this->artisan('plugin:list')
        ->assertFailed()
        ->expectsOutputToContain('state is corrupted');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{zip: string, id: string, class: string}
 */
function makeRuntimePluginArtifact(array $overrides = []): array
{
    $suffix = (string) random_int(100000, 999999);
    $id = "acme/runtime-{$suffix}";
    $classShort = "Runtime{$suffix}Plugin";
    $entryClass = "RuntimeArtifact\\{$classShort}";
    $manifest = [
        'id' => $id,
        'name' => 'Runtime Fixture',
        'version' => '1.0.0',
        'description' => 'Runtime fixture plugin.',
        'entry_class' => $entryClass,
        'core_version' => '^0.2',
        'php' => '^8.3',
        'dependencies' => [],
        ...$overrides,
    ];
    $composer = [
        'name' => $id,
        'type' => 'library',
        'require' => [
            'php' => '^8.3',
            'openkos/platform' => '^0.2',
        ],
        'autoload' => ['psr-4' => ['RuntimeArtifact\\' => 'src/']],
        'extra' => ['openkos' => ['plugin' => $entryClass]],
    ];
    $source = "<?php\n\nnamespace RuntimeArtifact;\n\nuse OpenKOS\\Platform\\OpenKOSManager;\nuse OpenKOS\\Platform\\Plugin\\Plugin;\nuse OpenKOS\\Platform\\Plugin\\PluginManifest;\n\nfinal class {$classShort} extends Plugin\n{\n    public function manifest(): PluginManifest\n    {\n        return new PluginManifest(\n            id: '{$manifest['id']}',\n            name: '{$manifest['name']}',\n            version: '{$manifest['version']}',\n            description: '{$manifest['description']}',\n            coreVersion: '{$manifest['core_version']}',\n            dependencies: [],\n        );\n    }\n\n    public function register(OpenKOSManager \$platform): void\n    {\n        \$platform->permissions()->register('runtime-fixture.view', 'Runtime Fixture');\n    }\n}\n";

    return makeZip([
        'manifest.json' => json_encode($manifest, JSON_THROW_ON_ERROR),
        'composer.json' => json_encode($composer, JSON_THROW_ON_ERROR),
        'composer.lock' => json_encode([
            'packages' => [['name' => 'openkos/platform', 'version' => '0.2.2']],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR),
        'src/'.$classShort.'.php' => $source,
        'vendor/autoload.php' => "<?php\nspl_autoload_register(static function (string \$class): void {\n    if (\$class === '{$entryClass}') {\n        require_once __DIR__.'/../src/{$classShort}.php';\n    }\n});\n",
        'vendor/composer/installed.php' => "<?php\nreturn ['versions' => []];\n",
    ], $id, $entryClass);
}

/**
 * @param  array<string, string>  $files
 * @return array{zip: string, id: string, class: string}
 */
function makeZip(array $files, string $id = 'acme/invalid', string $class = 'RuntimeArtifact\\InvalidPlugin'): array
{
    $directory = sys_get_temp_dir().'/openkos-zip-'.bin2hex(random_bytes(8));
    mkdir($directory, 0750, true);
    $zipPath = $directory.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $path => $contents) {
        $zip->addFromString($path, $contents);
    }

    $zip->close();
    File::deleteDirectory($directory);

    return ['zip' => $zipPath, 'id' => $id, 'class' => $class];
}
