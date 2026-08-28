<?php

use App\Services\Platform\PluginManagementService;
use App\Services\Platform\RuntimePluginStore;
use Illuminate\Support\Facades\File;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Permission\PermissionRegistry;
use OpenKOS\Plugins\Mail\MailPlugin;

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

    expect(app(PermissionRegistry::class)->all())
        ->not->toHaveKey('runtime-fixture.view')
        ->and(class_exists($artifact['class'], false))->toBeFalse();
});

it('does not execute disabled runtime plugins while listing them', function (): void {
    $artifact = makeRuntimePluginArtifact();
    $marker = sys_get_temp_dir().'/openkos-disabled-plugin-'.bin2hex(random_bytes(8));

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    $this->artisan('plugin:disable', ['id' => $artifact['id']])->assertSuccessful();

    $autoloadPath = $this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php';
    $autoload = file_get_contents($autoloadPath);
    $replacementCount = 0;
    file_put_contents($autoloadPath, str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
        $replacementCount,
    ));

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Disabled');

    $plugins = app(PluginManagementService::class)->catalog()['plugins'];

    expect(collect($plugins)->firstWhere('id', $artifact['id']))->toMatchArray([
        'status' => 'disabled',
        'enabled' => false,
    ]);

    expect(is_file($marker))->toBeFalse();
    File::delete($marker);
});

it('does not execute enabled runtime plugins while listing them', function (): void {
    $artifact = makeRuntimePluginArtifact();
    $marker = sys_get_temp_dir().'/openkos-enabled-list-'.bin2hex(random_bytes(8));

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    $autoloadPath = $this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php';
    $autoload = file_get_contents($autoloadPath);
    file_put_contents($autoloadPath, str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
    ));

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Enabled');

    expect(is_file($marker))->toBeFalse();
    File::delete($marker);
});

it('reports a disabled package with an unsafe tree without executing it', function (): void {
    $artifact = makeRuntimePluginArtifact();
    $outside = sys_get_temp_dir().'/openkos-disabled-symlink-'.bin2hex(random_bytes(8));
    file_put_contents($outside, "<?php\nfile_put_contents(".var_export($outside.'.loaded', true).", 'loaded');");

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    $this->artisan('plugin:disable', ['id' => $artifact['id']])->assertSuccessful();
    File::delete($this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php');
    symlink($outside, $this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php');

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Invalid (disabled)');

    expect(is_file($outside.'.loaded'))->toBeFalse();

    File::delete($outside);
    File::delete($outside.'.loaded');
});

it('does not recover runtime state while listing plugins', function (): void {
    mkdir($this->runtimePluginPath.'/.backup/recover', 0750, true);
    mkdir($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/.backup/recover/marker', 'keep');
    file_put_contents($this->runtimePluginPath.'/state.json', json_encode([
        'acme/recover' => ['enabled' => true],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recover',
        'staging' => '.staging/incoming',
        'backup' => '.backup/recover',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    $this->artisan('plugin:list')->assertSuccessful();

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeTrue()
        ->and(is_file($this->runtimePluginPath.'/.backup/recover/marker'))->toBeTrue();
});

it('validates updates in a fresh process after the previous class was loaded', function (): void {
    $first = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $first['zip']])->assertSuccessful();
    $this->bootPlatformWithIsolatedRegistries();

    $classShort = basename(str_replace('\\', '/', $first['class']));
    $second = makeRuntimePluginArtifact(['version' => '2.0.0'], $first['id'], $classShort);

    $this->artisan('plugin:install', ['zip' => $second['zip']])->assertSuccessful();

    expect(file_get_contents($this->runtimePluginPath.'/'.$first['id'].'/src/'.$classShort.'.php'))
        ->toContain("version: '2.0.0'");
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

it('does not overwrite stale runtime artifacts during installation', function (): void {
    $artifact = makeRuntimePluginArtifact();
    File::makeDirectory($this->runtimePluginPath.'/.staging/stale', 0750, true);
    file_put_contents($this->runtimePluginPath.'/.staging/stale/marker', 'keep');

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])
        ->assertFailed()
        ->expectsOutputToContain('Orphaned runtime artifacts');

    expect(is_file($this->runtimePluginPath.'/.staging/stale/marker'))->toBeTrue()
        ->and(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse();
});

it('counts archive directories toward the entry limit', function (): void {
    config(['platform.runtime.max_files' => 1]);
    $zip = makeDirectoryHeavyZip();

    $this->artisan('plugin:install', ['zip' => $zip])->assertFailed();
});

it('validates bundled dependency versions and rejects bundled host packages', function (): void {
    $artifact = makeRuntimePluginArtifact(
        id: null,
        classShort: null,
        bundledPackages: ['acme/dependency' => '1.0.0'],
        additionalRequirements: ['acme/dependency' => '^2.0'],
    );

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])
        ->assertFailed()
        ->expectsOutputToContain('Bundled package [acme/dependency] version [1.0.0]');

    $hostPackageArtifact = makeRuntimePluginArtifact(
        id: null,
        classShort: null,
        bundledPackages: ['laravel/framework' => '13.0.0'],
    );

    $this->artisan('plugin:install', ['zip' => $hostPackageArtifact['zip']])
        ->assertFailed()
        ->expectsOutputToContain('must not bundle host package [laravel/framework]');
});

it('reports an enabled package with a malformed manifest without booting it', function (): void {
    $artifact = makeRuntimePluginArtifact();

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])->assertSuccessful();
    file_put_contents($this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json', '{broken');

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Invalid (enabled)');
});

it('skips a runtime plugin that conflicts with an explicit plugin', function (): void {
    $artifact = makeRuntimePluginArtifact(id: 'openkos/mail');
    $packagePath = $this->runtimePluginPath.'/'.$artifact['id'];
    File::makeDirectory($packagePath, 0750, true);
    $zip = new ZipArchive;
    $zip->open($artifact['zip']);
    $zip->extractTo($packagePath);
    $zip->close();
    app(RuntimePluginStore::class)->writeState([
        $artifact['id'] => ['enabled' => true],
    ]);
    $marker = sys_get_temp_dir().'/openkos-conflicting-plugin-'.bin2hex(random_bytes(8));
    $autoloadPath = $packagePath.'/vendor/autoload.php';
    $autoload = file_get_contents($autoloadPath);
    $replacementCount = 0;
    file_put_contents($autoloadPath, str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
        $replacementCount,
    ));

    config(['platform.plugins' => [MailPlugin::class]]);

    expect(fn (): mixed => $this->bootPlatformWithIsolatedRegistries())
        ->not->toThrow(RuntimeException::class);
    expect(app(PermissionRegistry::class)->all())->not->toHaveKey('runtime-fixture.view')
        ->and(is_file($marker))->toBeFalse();
    File::delete($marker);
});

it('does not execute a conflicting runtime plugin while listing it', function (): void {
    $artifact = makeRuntimePluginArtifact(id: 'openkos/mail');
    $packagePath = $this->runtimePluginPath.'/'.$artifact['id'];
    File::makeDirectory($packagePath, 0750, true);
    $zip = new ZipArchive;
    $zip->open($artifact['zip']);
    $zip->extractTo($packagePath);
    $zip->close();
    app(RuntimePluginStore::class)->writeState([
        $artifact['id'] => ['enabled' => true],
    ]);
    $marker = sys_get_temp_dir().'/openkos-conflicting-list-'.bin2hex(random_bytes(8));
    $autoloadPath = $packagePath.'/vendor/autoload.php';
    $autoload = file_get_contents($autoloadPath);
    $replacementCount = 0;
    file_put_contents($autoloadPath, str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
        $replacementCount,
    ));

    config(['platform.plugins' => [MailPlugin::class]]);

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Conflict');

    expect(is_file($marker))->toBeFalse();
    File::delete($marker);
});

it('does not execute duplicate runtime entry classes during discovery', function (): void {
    $first = makeRuntimePluginArtifact(
        ['id' => 'acme/duplicate-a'],
        classShort: 'SharedDuplicatePlugin',
    );
    $second = makeRuntimePluginArtifact(
        ['id' => 'acme/duplicate-b'],
        classShort: 'SharedDuplicatePlugin',
    );

    $this->artisan('plugin:install', ['zip' => $first['zip']])->assertSuccessful();
    $secondPath = $this->runtimePluginPath.'/'.$second['id'];
    File::makeDirectory($secondPath, 0750, true);
    $zip = new ZipArchive;
    $zip->open($second['zip']);
    $zip->extractTo($secondPath);
    $zip->close();
    app(RuntimePluginStore::class)->writeState([
        $first['id'] => ['enabled' => true],
        $second['id'] => ['enabled' => true],
    ]);

    $markers = [
        sys_get_temp_dir().'/openkos-duplicate-a-'.bin2hex(random_bytes(8)),
        sys_get_temp_dir().'/openkos-duplicate-b-'.bin2hex(random_bytes(8)),
    ];

    foreach ([$first, $second] as $index => $artifact) {
        $autoloadPath = $this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php';
        $autoload = file_get_contents($autoloadPath);
        $replacementCount = 0;
        file_put_contents($autoloadPath, str_replace(
            '<?php',
            "<?php\nfile_put_contents(".var_export($markers[$index], true).", 'loaded');",
            $autoload,
            $replacementCount,
        ));
    }

    $this->bootPlatformWithIsolatedRegistries();

    expect(is_file($markers[0]))->toBeFalse()
        ->and(is_file($markers[1]))->toBeFalse();

    File::delete($markers[0]);
    File::delete($markers[1]);
});

it('rejects enabling a duplicate runtime entry class before loading it', function (): void {
    $first = makeRuntimePluginArtifact(
        ['id' => 'acme/duplicate-enable-a'],
        classShort: 'SharedEnablePlugin',
    );
    $second = makeRuntimePluginArtifact(
        ['id' => 'acme/duplicate-enable-b'],
        classShort: 'SharedEnablePlugin',
    );

    $this->artisan('plugin:install', ['zip' => $first['zip']])->assertSuccessful();
    $secondPath = $this->runtimePluginPath.'/'.$second['id'];
    File::makeDirectory($secondPath, 0750, true);
    $zip = new ZipArchive;
    $zip->open($second['zip']);
    $zip->extractTo($secondPath);
    $zip->close();
    app(RuntimePluginStore::class)->writeState([
        $first['id'] => ['enabled' => true],
        $second['id'] => ['enabled' => false],
    ]);

    $marker = sys_get_temp_dir().'/openkos-duplicate-enable-'.bin2hex(random_bytes(8));
    $autoloadPath = $secondPath.'/vendor/autoload.php';
    $autoload = file_get_contents($autoloadPath);
    file_put_contents($autoloadPath, str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
    ));

    $this->artisan('plugin:enable', ['id' => $second['id']])
        ->assertFailed()
        ->expectsOutputToContain('conflicts');

    expect(is_file($marker))->toBeFalse()
        ->and(app(RuntimePluginStore::class)->readState()[$second['id']]['enabled'])->toBeFalse();

    File::delete($marker);
});

it('rejects an entry-class conflict before activating a runtime plugin', function (): void {
    $artifact = makeRuntimePluginArtifact();
    $marker = sys_get_temp_dir().'/openkos-static-conflict-'.bin2hex(random_bytes(8));
    $zip = new ZipArchive;
    $zip->open($artifact['zip']);
    $autoload = $zip->getFromName('vendor/autoload.php');
    $zip->addFromString('vendor/autoload.php', str_replace(
        '<?php',
        "<?php\nfile_put_contents(".var_export($marker, true).", 'loaded');",
        $autoload,
    ));
    $zip->close();
    config(['platform.plugins' => [$artifact['class']]]);

    $this->artisan('plugin:install', ['zip' => $artifact['zip']])
        ->assertFailed()
        ->expectsOutputToContain('conflicts');

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse()
        ->and(is_file($marker))->toBeFalse();

    File::delete($marker);
});

it('surfaces corrupted runtime state instead of treating it as disabled', function (): void {
    mkdir($this->runtimePluginPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Orphaned state');
});

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<string, string>  $bundledPackages
 * @param  array<string, string>  $additionalRequirements
 * @return array{zip: string, id: string, class: string}
 */
function makeRuntimePluginArtifact(
    array $overrides = [],
    ?string $id = null,
    ?string $classShort = null,
    array $bundledPackages = [],
    array $additionalRequirements = [],
): array {
    $suffix = (string) random_int(100000, 999999);
    $id ??= "acme/runtime-{$suffix}";
    $classShort ??= "Runtime{$suffix}Plugin";
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
        'name' => $manifest['id'],
        'type' => 'library',
        'require' => [
            'php' => '^8.3',
            'openkos/platform' => '^0.2',
            ...$additionalRequirements,
        ],
        'autoload' => ['psr-4' => ['RuntimeArtifact\\' => 'src/']],
        'extra' => ['openkos' => ['plugin' => $entryClass]],
    ];
    $source = "<?php\n\nnamespace RuntimeArtifact;\n\nuse OpenKOS\\Platform\\OpenKOSManager;\nuse OpenKOS\\Platform\\Plugin\\Plugin;\nuse OpenKOS\\Platform\\Plugin\\PluginManifest;\n\nfinal class {$classShort} extends Plugin\n{\n    public function manifest(): PluginManifest\n    {\n        return new PluginManifest(\n            id: '{$manifest['id']}',\n            name: '{$manifest['name']}',\n            version: '{$manifest['version']}',\n            description: '{$manifest['description']}',\n            coreVersion: '{$manifest['core_version']}',\n            dependencies: [],\n        );\n    }\n\n    public function register(OpenKOSManager \$platform): void\n    {\n        \$platform->permissions()->register('runtime-fixture.view', 'Runtime Fixture');\n    }\n}\n";

    return makeZip([
        'manifest.json' => json_encode($manifest, JSON_THROW_ON_ERROR),
        'composer.json' => json_encode($composer, JSON_THROW_ON_ERROR),
        'composer.lock' => json_encode([
            'packages' => [
                ['name' => 'openkos/platform', 'version' => '0.2.2'],
                ...array_map(
                    fn (string $version, string $name): array => ['name' => $name, 'version' => $version],
                    $bundledPackages,
                    array_keys($bundledPackages),
                ),
            ],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR),
        'src/'.$classShort.'.php' => $source,
        'vendor/autoload.php' => "<?php\nspl_autoload_register(static function (string \$class): void {\n    if (\$class === '{$entryClass}') {\n        require_once __DIR__.'/../src/{$classShort}.php';\n    }\n});\n",
        'vendor/composer/installed.php' => "<?php\nreturn ['versions' => ".var_export(array_map(
            fn (string $version): array => ['pretty_version' => $version],
            $bundledPackages,
        ), true)."];\n",
    ], $manifest['id'], $entryClass);
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

function makeDirectoryHeavyZip(): string
{
    $directory = sys_get_temp_dir().'/openkos-directory-heavy-'.bin2hex(random_bytes(8));
    mkdir($directory, 0750, true);
    $zipPath = $directory.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addEmptyDir('src');
    $zip->addFromString('manifest.json', '{}');
    $zip->close();
    File::deleteDirectory($directory);

    return $zipPath;
}
