<?php

use App\Models\User;
use App\Services\Platform\PluginInstaller;
use App\Services\Platform\RuntimePluginDiscovery;
use App\Services\Platform\RuntimePluginGraphValidator;
use App\Services\Platform\RuntimePluginStore;
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

it('rejects disabling or removing a runtime dependency of an enabled plugin', function (): void {
    $dependency = makePluginSettingsArtifact();
    $dependent = makePluginSettingsArtifact(['dependencies' => [$dependency['id']]]);
    app(PluginInstaller::class)->install($dependency['zip']);
    app(PluginInstaller::class)->install($dependent['zip']);
    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $dependency['id']);

    $this->actingAs($owner)
        ->post(route('settings.plugins.disable', ['vendor' => $vendor, 'package' => $package]))
        ->assertSessionHasErrors('plugin', "Cannot disable {$dependency['id']} because {$dependent['id']} depends on it.");

    $this->actingAs($owner)
        ->delete(route('settings.plugins.destroy', ['vendor' => $vendor, 'package' => $package]))
        ->assertSessionHasErrors('plugin', "Cannot remove {$dependency['id']} because {$dependent['id']} depends on it.");

    expect(app(RuntimePluginStore::class)->readState())
        ->toMatchArray([$dependency['id'] => ['enabled' => true]])
        ->and(is_dir($this->runtimePluginPath.'/'.$dependency['id']))->toBeTrue();
});

it('allows explicit force recovery from an invalid dependency cycle', function (): void {
    $first = makePluginSettingsArtifact([
        'id' => 'settings/cycle-a',
        'dependencies' => ['settings/cycle-b'],
    ]);
    $second = makePluginSettingsArtifact([
        'id' => 'settings/cycle-b',
        'dependencies' => [$first['id']],
    ]);

    foreach ([$first, $second] as $artifact) {
        $path = $this->runtimePluginPath.'/'.$artifact['id'];
        File::makeDirectory($path, 0750, true);
        $zip = new ZipArchive;
        $zip->open($artifact['zip']);
        $zip->extractTo($path);
        $zip->close();
    }

    app(RuntimePluginStore::class)->writeState([
        $first['id'] => ['enabled' => true],
        $second['id'] => ['enabled' => true],
    ]);

    $this->artisan('plugin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Invalid (enabled)');

    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $first['id']);

    $this->actingAs($owner)
        ->post(route('settings.plugins.disable', ['vendor' => $vendor, 'package' => $package]))
        ->assertSessionHasErrors('plugin');

    $this->actingAs($owner)
        ->post(route('settings.plugins.disable', ['vendor' => $vendor, 'package' => $package]), ['force' => true])
        ->assertRedirect(route('settings.plugins.index'));

    expect(app(RuntimePluginStore::class)->readState())
        ->toMatchArray([$first['id'] => ['enabled' => false]]);
});

it('does not let an unrelated broken runtime plugin block a candidate', function (): void {
    $broken = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($broken['zip']);
    File::delete($this->runtimePluginPath.'/'.$broken['id'].'/manifest.json');

    $candidate = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($candidate['zip']);
    app(PluginInstaller::class)->disable($candidate['id']);
    app(PluginInstaller::class)->enable($candidate['id']);

    expect(is_dir($this->runtimePluginPath.'/'.$candidate['id']))->toBeTrue()
        ->and(app(RuntimePluginStore::class)->readState()[$candidate['id']]['enabled'])->toBeTrue();
});

it('marks enabled runtime dependency cycles as unavailable', function (): void {
    $report = app(RuntimePluginGraphValidator::class)->validate([
        'settings/cycle-a' => [
            'metadata' => [
                'id' => 'settings/cycle-a',
                'entry_class' => 'SettingsRuntime\\CycleAPlugin',
                'core_version' => '*',
                'dependencies' => ['settings/cycle-b'],
            ],
            'enabled' => true,
        ],
        'settings/cycle-b' => [
            'metadata' => [
                'id' => 'settings/cycle-b',
                'entry_class' => 'SettingsRuntime\\CycleBPlugin',
                'core_version' => '*',
                'dependencies' => ['settings/cycle-a'],
            ],
            'enabled' => true,
        ],
    ], []);

    expect($report['loadable'])->toBe([])
        ->and($report['issues'])->toMatchArray([
            'settings/cycle-a' => ['status' => 'broken', 'error' => 'Runtime plugin dependency cycle detected.'],
            'settings/cycle-b' => ['status' => 'broken', 'error' => 'Runtime plugin dependency cycle detected.'],
        ]);
});

it('marks duplicate runtime entry classes as conflicts', function (): void {
    $metadata = fn (string $id): array => [
        'id' => $id,
        'entry_class' => 'SettingsRuntime\\DuplicatePlugin',
        'core_version' => '*',
        'dependencies' => [],
    ];
    $report = app(RuntimePluginGraphValidator::class)->validate([
        'settings/duplicate-a' => ['metadata' => $metadata('settings/duplicate-a'), 'enabled' => true],
        'settings/duplicate-b' => ['metadata' => $metadata('settings/duplicate-b'), 'enabled' => true],
    ], []);

    expect($report['loadable'])->toBe([])
        ->and($report['issues'])->toMatchArray([
            'settings/duplicate-a' => [
                'status' => 'conflict',
                'error' => 'Runtime plugin [settings/duplicate-a] conflicts with another runtime plugin entry class [SettingsRuntime\\DuplicatePlugin].',
            ],
            'settings/duplicate-b' => [
                'status' => 'conflict',
                'error' => 'Runtime plugin [settings/duplicate-b] conflicts with another runtime plugin entry class [SettingsRuntime\\DuplicatePlugin].',
            ],
        ]);
});

it('treats runtime entry class names as case-insensitive', function (): void {
    $report = app(RuntimePluginGraphValidator::class)->validate([
        'settings/case-a' => [
            'metadata' => [
                'id' => 'settings/case-a',
                'entry_class' => 'SettingsRuntime\\CasePlugin',
                'core_version' => '*',
                'dependencies' => [],
            ],
            'enabled' => true,
        ],
        'settings/case-b' => [
            'metadata' => [
                'id' => 'settings/case-b',
                'entry_class' => 'settingsruntime\\caseplugin',
                'core_version' => '*',
                'dependencies' => [],
            ],
            'enabled' => true,
        ],
    ], []);

    expect($report['loadable'])->toBe([])
        ->and($report['issues'])->toHaveKeys(['settings/case-a', 'settings/case-b']);
});

it('keeps the plugins page available when a runtime dependency is missing or disabled', function (): void {
    $dependency = makePluginSettingsArtifact();
    $dependent = makePluginSettingsArtifact(['dependencies' => [$dependency['id']]]);
    app(PluginInstaller::class)->install($dependency['zip']);
    app(PluginInstaller::class)->install($dependent['zip']);
    app(RuntimePluginStore::class)->withLock(function (RuntimePluginStore $store) use ($dependency): void {
        $store->setEnabled($dependency['id'], false);
    });

    $missing = makePluginSettingsArtifact(['dependencies' => ['missing/plugin']]);
    $missingPath = $this->runtimePluginPath.'/'.$missing['id'];
    File::makeDirectory($missingPath, 0750, true);
    $zip = new ZipArchive;
    $zip->open($missing['zip']);
    $zip->extractTo($missingPath);
    $zip->close();
    $state = app(RuntimePluginStore::class)->readState();
    $state[$missing['id']] = ['enabled' => true];
    app(RuntimePluginStore::class)->writeState($state);

    expect(app(RuntimePluginDiscovery::class)->discover())->toBe([]);

    $plugins = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('id', $missing['id']))->toMatchArray([
        'managed_id' => $missing['id'],
        'status' => 'broken',
        'can_enable' => false,
        'can_remove' => true,
    ])
        ->and(collect($plugins)->firstWhere('id', $dependent['id']))->toMatchArray([
            'status' => 'broken',
            'can_enable' => false,
        ])
        ->and(collect($plugins)->firstWhere('id', $dependent['id'])['error'])
        ->toContain("disabled plugin [{$dependency['id']}]");
});

it('surfaces a runtime plugin that is incompatible with the current core', function (): void {
    $artifact = makePluginSettingsArtifact(['core_version' => '^9.0']);
    $path = $this->runtimePluginPath.'/'.$artifact['id'];
    File::makeDirectory($path, 0750, true);
    $zip = new ZipArchive;
    $zip->open($artifact['zip']);
    $zip->extractTo($path);
    $zip->close();
    file_put_contents($this->runtimePluginPath.'/state.json', json_encode([
        $artifact['id'] => ['enabled' => true],
    ], JSON_THROW_ON_ERROR));

    $plugins = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('id', $artifact['id']))->toMatchArray([
        'status' => 'incompatible',
        'can_enable' => false,
        'can_disable' => true,
        'can_remove' => true,
    ]);
});

it('uses the managed package identity to recover a mismatched manifest', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    $manifestPath = $this->runtimePluginPath.'/'.$artifact['id'].'/manifest.json';
    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['id'] = 'other/declared-id';
    file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $artifact['id']);

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', $artifact['id']))->toMatchArray([
        'id' => $artifact['id'],
        'managed_id' => $artifact['id'],
        'declared_id' => 'other/declared-id',
        'status' => 'broken',
        'can_remove' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.destroy', ['vendor' => $vendor, 'package' => $package]))
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse();
});

it('keeps a runtime package removable when state metadata is corrupt', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');
    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $artifact['id']);

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', $artifact['id']))->toMatchArray([
        'status' => 'broken',
        'can_enable' => false,
        'can_disable' => false,
        'can_remove' => false,
        'can_force_recovery' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.destroy', ['vendor' => $vendor, 'package' => $package]), ['force' => true])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/state.json'))->toBeFalse();
});

it('surfaces and cleans orphaned runtime state without a package row', function (): void {
    File::makeDirectory($this->runtimePluginPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('status', 'orphaned_state'))->toMatchArray([
        'managed_id' => null,
        'can_cleanup' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'))
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_file($this->runtimePluginPath.'/state.json'))->toBeFalse();
});

it('surfaces and cleans unrecoverable runtime recovery without a package row', function (): void {
    File::makeDirectory($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'settings/missing-backup',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('status', 'unrecoverable_recovery'))->toMatchArray([
        'managed_id' => 'settings/missing-backup',
        'can_cleanup' => true,
        'cleanup_key' => 'recovery:settings/missing-backup',
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'recovery:settings/missing-backup',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse();
});

it('offers cleanup for pending recovery without a package row', function (): void {
    File::makeDirectory($this->runtimePluginPath.'/.backup', 0750, true);
    File::makeDirectory($this->runtimePluginPath.'/.backup/settings-pending', 0750, true);
    File::makeDirectory($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'settings/pending-recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/settings-pending',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', 'settings/pending-recovery'))->toMatchArray([
        'status' => 'pending_recovery',
        'can_cleanup' => true,
        'cleanup_key' => 'recovery:settings/pending-recovery',
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'recovery:settings/pending-recovery',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.backup'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse();
});

it('cleans an orphaned recovery marker by identity without touching another package', function (): void {
    $other = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($other['zip']);
    File::makeDirectory($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'settings/missing-recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', 'settings/missing-recovery'))->toMatchArray([
        'status' => 'unrecoverable_recovery',
        'can_cleanup' => true,
    ]);
    expect(collect($plugins)->firstWhere('managed_id', $other['id']))->toMatchArray([
        'can_remove' => true,
        'can_force_recovery' => false,
    ]);

    [$otherVendor, $otherPackage] = explode('/', $other['id']);

    $this->actingAs($owner)
        ->post(route('settings.plugins.disable', ['vendor' => $otherVendor, 'package' => $otherPackage]))
        ->assertRedirect(route('settings.plugins.index'));

    expect(app(RuntimePluginStore::class)->readState()[$other['id']]['enabled'])->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'recovery:settings/missing-recovery',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_dir($this->runtimePluginPath.'/'.$other['id']))->toBeTrue()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging/incoming'))->toBeFalse();
});

it('surfaces unknown recovery metadata independently from installed packages', function (): void {
    $first = makePluginSettingsArtifact();
    $second = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($first['zip']);
    app(PluginInstaller::class)->install($second['zip']);
    file_put_contents($this->runtimePluginPath.'/recovery.json', '{broken');
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('cleanup_key', 'orphaned-recovery'))->toMatchArray([
        'status' => 'unrecoverable_recovery',
        'managed_id' => null,
        'can_cleanup' => true,
    ])
        ->and(collect($plugins)->firstWhere('managed_id', $first['id']))->toMatchArray([
            'can_remove' => true,
            'can_force_recovery' => false,
        ])
        ->and(collect($plugins)->firstWhere('managed_id', $second['id']))->toMatchArray([
            'can_remove' => true,
            'can_force_recovery' => false,
        ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'orphaned-recovery',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/'.$first['id']))->toBeTrue()
        ->and(is_dir($this->runtimePluginPath.'/'.$second['id']))->toBeTrue();
});

it('surfaces and cleans stale runtime artifacts without touching a package', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    File::makeDirectory($this->runtimePluginPath.'/.staging/stale', 0750, true);
    file_put_contents($this->runtimePluginPath.'/.state-stale.tmp', 'stale');
    $owner = User::factory()->owner()->create();

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('status', 'orphaned_runtime_artifact'))->toMatchArray([
        'managed_id' => null,
        'can_cleanup' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'orphaned-artifacts',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeTrue()
        ->and(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/.state-stale.tmp'))->toBeFalse();
});

it('surfaces and removes a symlinked runtime package without following it', function (): void {
    $outside = sys_get_temp_dir().'/openkos-settings-outside-'.bin2hex(random_bytes(8));
    File::makeDirectory($outside, 0750, true);
    File::makeDirectory($this->runtimePluginPath.'/settings', 0750, true);
    symlink($outside, $this->runtimePluginPath.'/settings/runtime');
    $owner = User::factory()->owner()->create();

    try {
        $plugins = $this->actingAs($owner)
            ->get(route('settings.plugins.index'))
            ->assertSuccessful()
            ->inertiaProps('plugins');

        expect(collect($plugins)->firstWhere('managed_id', 'settings/runtime'))->toMatchArray([
            'status' => 'broken',
            'can_cleanup' => true,
        ]);

        $this->actingAs($owner)
            ->delete(route('settings.plugins.recovery.cleanup'), [
                'cleanup_key' => 'package:settings/runtime',
            ])
            ->assertRedirect(route('settings.plugins.index'));

        expect(is_link($this->runtimePluginPath.'/settings/runtime'))->toBeFalse()
            ->and(is_dir($outside))->toBeTrue();
    } finally {
        File::deleteDirectory($outside);
        File::delete($this->runtimePluginPath.'/settings/runtime');
    }
});

it('surfaces and removes a symlinked runtime vendor without following it', function (): void {
    $outside = sys_get_temp_dir().'/openkos-settings-vendor-'.bin2hex(random_bytes(8));
    File::makeDirectory($outside, 0750, true);
    File::makeDirectory($this->runtimePluginPath, 0750, true);
    symlink($outside, $this->runtimePluginPath.'/settings');
    app(RuntimePluginStore::class)->writeState(['settings/runtime' => ['enabled' => true]]);
    $owner = User::factory()->owner()->create();

    try {
        $plugins = $this->actingAs($owner)
            ->get(route('settings.plugins.index'))
            ->assertSuccessful()
            ->inertiaProps('plugins');

        expect(collect($plugins)->firstWhere('cleanup_key', 'vendor:settings'))->toMatchArray([
            'status' => 'broken',
            'can_cleanup' => true,
        ])
            ->and(collect($plugins)->firstWhere('managed_id', 'settings/runtime'))->toMatchArray([
                'status' => 'missing_package',
                'can_remove' => false,
                'can_force_recovery' => false,
            ]);

        $this->actingAs($owner)
            ->delete(route('settings.plugins.recovery.cleanup'), [
                'cleanup_key' => 'vendor:settings',
            ])
            ->assertRedirect(route('settings.plugins.index'));

        expect(is_link($this->runtimePluginPath.'/settings'))->toBeFalse()
            ->and(is_dir($outside))->toBeTrue();
    } finally {
        File::deleteDirectory($outside);
        File::delete($this->runtimePluginPath.'/settings');
    }
});

it('keeps a runtime package removable when recovery metadata is corrupt', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    file_put_contents($this->runtimePluginPath.'/recovery.json', '{broken');
    $owner = User::factory()->owner()->create();
    [$vendor, $package] = explode('/', $artifact['id']);

    $plugins = $this->actingAs($owner)
        ->get(route('settings.plugins.index'))
        ->assertSuccessful()
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', $artifact['id']))->toMatchArray([
        'status' => 'enabled',
        'can_disable' => true,
        'can_remove' => true,
        'can_force_recovery' => false,
    ]);

    expect(collect($plugins)->firstWhere('cleanup_key', 'orphaned-recovery'))->toMatchArray([
        'can_cleanup' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.plugins.recovery.cleanup'), [
            'cleanup_key' => 'orphaned-recovery',
        ])
        ->assertRedirect(route('settings.plugins.index'));

    $this->actingAs($owner)
        ->delete(route('settings.plugins.destroy', ['vendor' => $vendor, 'package' => $package]))
        ->assertRedirect(route('settings.plugins.index'));

    expect(is_dir($this->runtimePluginPath.'/'.$artifact['id']))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse();
});

it('does not offer disable for a package missing from the managed directory', function (): void {
    $artifact = makePluginSettingsArtifact();
    app(PluginInstaller::class)->install($artifact['zip']);
    File::deleteDirectory($this->runtimePluginPath.'/'.$artifact['id']);

    $plugins = $this->actingAs(User::factory()->owner()->create())
        ->get(route('settings.plugins.index'))
        ->inertiaProps('plugins');

    expect(collect($plugins)->firstWhere('managed_id', $artifact['id']))->toMatchArray([
        'status' => 'missing_package',
        'can_disable' => false,
        'can_remove' => true,
    ]);
});

/** @return array{zip: string, id: string, class: string} */
function makePluginSettingsArtifact(array $overrides = []): array
{
    $suffix = (string) random_int(100000, 999999);
    $id = $overrides['id'] ?? "settings/runtime-{$suffix}";
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
        ...$overrides,
    ];
    $dependencies = var_export($manifest['dependencies'], true);
    $source = "<?php\n\nnamespace SettingsRuntime;\n\nuse OpenKOS\\Platform\\OpenKOSManager;\nuse OpenKOS\\Platform\\Plugin\\Plugin;\nuse OpenKOS\\Platform\\Plugin\\PluginManifest;\n\nfinal class {$classShort} extends Plugin\n{\n    public function manifest(): PluginManifest\n    {\n        return new PluginManifest(\n            id: '{$id}',\n            name: 'Settings Runtime Fixture',\n            version: '1.0.0',\n            description: 'Settings runtime fixture.',\n            coreVersion: '^0.2',\n        );\n    }\n\n    public function register(OpenKOSManager \$platform): void {}\n}\n";
    $source = str_replace("coreVersion: '^0.2',", "coreVersion: '{$manifest['core_version']}',\n            dependencies: {$dependencies},", $source);

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
