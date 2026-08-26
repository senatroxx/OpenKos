<?php

use App\Models\User;
use App\Services\Platform\PluginInstaller;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Plugins\Mail\MailPlugin;

beforeEach(function (): void {
    $this->runtimePluginPath = sys_get_temp_dir().'/openkos-settings-runtime-'.bin2hex(random_bytes(8));
    $this->originalRuntimePath = config('platform.runtime.path');

    config([
        'platform.runtime.path' => $this->runtimePluginPath,
        'platform.plugins' => [],
    ]);
    app()->forgetInstance(PluginDiscovery::class);
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
});

afterEach(function (): void {
    File::deleteDirectory($this->runtimePluginPath);
    config([
        'platform.runtime.path' => $this->originalRuntimePath,
    ]);
});

it('redirects guests and forbids non-owners from plugin management', function (): void {
    $this->get(route('settings.plugins.index'))
        ->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.plugins.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('settings.plugins.install'))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('settings.plugins.destroy', ['vendor' => 'acme', 'package' => 'runtime']))
        ->assertForbidden();
});

it('shows owners a safe plugin catalog without creating runtime storage', function (): void {
    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/plugins')
        ->has('plugins')
        ->where('error', null)
        ->where('max_upload_bytes', 64 * 1024 * 1024));
    $response->assertDontSee($this->runtimePluginPath);
    expect(is_dir($this->runtimePluginPath))->toBeFalse();
});

it('installs a valid upload and always removes the temporary file', function (): void {
    $artifact = makePluginSettingsArtifact();
    $composerBefore = file_get_contents(base_path('composer.json'));
    $lockBefore = file_get_contents(base_path('composer.lock'));
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.plugins.install'), [
            'file' => new UploadedFile($artifact['zip'], 'runtime.zip', 'application/zip', null, true),
        ])
        ->assertRedirect(route('settings.plugins.index'));

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->inertiaProps('plugins');

    expect(Storage::disk('local')->allFiles('plugin-uploads'))->toBe([])
        ->and(file_get_contents(base_path('composer.json')))->toBe($composerBefore)
        ->and(file_get_contents(base_path('composer.lock')))->toBe($lockBefore)
        ->and(is_file($this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json'))->toBeTrue()
        ->and(collect($plugins)->firstWhere('id', $artifact['id']))->toMatchArray([
            'source' => 'runtime',
            'status' => 'enabled',
        ]);
});

it('cleans up an upload when the installer rejects it', function (): void {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('settings.plugins.install'), [
            'file' => UploadedFile::fake()->create('runtime.zip', 10, 'application/zip'),
        ])
        ->assertSessionHasErrors('file');

    expect(Storage::disk('local')->allFiles('plugin-uploads'))->toBe([]);
});

it('delegates runtime lifecycle actions and reports restart requirements', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $artifact['id']);

    $this->actingAs($owner)
        ->post(route('settings.plugins.disable', ['vendor' => $vendor, 'package' => $package]))
        ->assertRedirect(route('settings.plugins.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Plugin disabled. Restart required for changes to take effect.',
        ]);

    $this->actingAs($owner)
        ->post(route('settings.plugins.enable', ['vendor' => $vendor, 'package' => $package]))
        ->assertRedirect(route('settings.plugins.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Plugin enabled. Restart required for changes to take effect.',
        ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.destroy', ['vendor' => $vendor, 'package' => $package]))
        ->assertRedirect(route('settings.plugins.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Plugin removed. Restart required for changes to take effect.',
        ]);

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse();
});

it('rejects non-zip uploads at the HTTP boundary', function (): void {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('settings.plugins.install'), [
            'file' => UploadedFile::fake()->create('runtime.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('file');
});

it('shows legacy plugins without exposing runtime lifecycle actions', function (): void {
    config(['platform.plugins' => [MailPlugin::class]]);

    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'));
    $plugins = $response->inertiaProps('plugins');
    $mail = collect($plugins)->firstWhere('id', 'openkos/mail');

    expect($mail)->toMatchArray([
        'source' => 'explicit',
        'status' => 'legacy',
        'can_enable' => false,
        'can_disable' => false,
        'can_remove' => false,
    ]);
});

it('shows runtime and explicit duplicates as a recoverable conflict', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    require_once $this->runtimePluginPath.'/'.$artifact['id'].'/vendor/autoload.php';
    config(['platform.plugins' => [$artifact['class']]]);

    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'));
    $plugins = $response->inertiaProps('plugins');
    $runtime = collect($plugins)->firstWhere('source', 'runtime');

    expect($runtime)->toMatchArray([
        'id' => $artifact['id'],
        'status' => 'conflict',
        'can_enable' => false,
        'can_remove' => true,
    ]);
});

it('shows incomplete runtime packages with safe recovery guidance', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    File::delete($this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json');

    $response = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'));
    $plugins = $response->inertiaProps('plugins');
    $runtime = collect($plugins)->firstWhere('id', $artifact['id']);

    expect($runtime)->toMatchArray([
        'status' => 'broken',
        'can_enable' => false,
        'can_remove' => true,
    ]);
    $response->assertDontSee($this->runtimePluginPath);
});

/** @return array{zip: string, id: string, class: string} */
function makePluginSettingsArtifact(): array
{
    $suffix = (string) random_int(100000, 999999);
    $id = "settings/runtime-{$suffix}";
    $classShort = "Runtime{$suffix}Plugin";
    $entryClass = "SettingsRuntime\\{$classShort}";
    $manifest = [
        'id' => $id,
        'name' => 'Settings Runtime Fixture',
        'version' => '1.0.0',
        'description' => 'Settings runtime fixture.',
        'entry_class' => $entryClass,
        'core_version' => '^0.2',
        'php' => '^8.3',
        'dependencies' => [],
    ];
    $source = "<?php\n\nnamespace SettingsRuntime;\n\nuse OpenKOS\\Platform\\OpenKOSManager;\nuse OpenKOS\\Platform\\Plugin\\Plugin;\nuse OpenKOS\\Platform\\Plugin\\PluginManifest;\n\nfinal class {$classShort} extends Plugin\n{\n    public function manifest(): PluginManifest\n    {\n        return new PluginManifest(\n            id: '{$id}',\n            name: 'Settings Runtime Fixture',\n            version: '1.0.0',\n            description: 'Settings runtime fixture.',\n            coreVersion: '^0.2',\n        );\n    }\n\n    public function register(OpenKOSManager \$platform): void {}\n}\n";

    $directory = sys_get_temp_dir().'/openkos-settings-zip-'.bin2hex(random_bytes(8));
    mkdir($directory, 0750, true);
    $zipPath = $directory.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $files = [
        'manifest.json' => json_encode($manifest, JSON_THROW_ON_ERROR),
        'composer.json' => json_encode([
            'name' => $id,
            'type' => 'library',
            'require' => ['php' => '^8.3', 'openkos/platform' => '^0.2'],
            'autoload' => ['psr-4' => ['SettingsRuntime\\' => 'src/']],
            'extra' => ['openkos' => ['plugin' => $entryClass]],
        ], JSON_THROW_ON_ERROR),
        'composer.lock' => json_encode([
            'packages' => [['name' => 'openkos/platform', 'version' => '0.2.2']],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR),
        'src/'.$classShort.'.php' => $source,
        'vendor/autoload.php' => "<?php\nspl_autoload_register(static function (string \$class): void {\n    if (\$class === '{$entryClass}') {\n        require_once __DIR__.'/../src/{$classShort}.php';\n    }\n});\n",
        'vendor/composer/installed.php' => "<?php\nreturn ['versions' => []];\n",
    ];

    foreach ($files as $path => $contents) {
        $zip->addFromString($path, $contents);
    }

    $zip->close();
    File::deleteDirectory($directory);

    return ['zip' => $zipPath, 'id' => $id, 'class' => $entryClass];
}
