<?php

use App\Services\Platform\RuntimePluginStore;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->runtimePluginPath = sys_get_temp_dir().'/openkos-runtime-store-'.bin2hex(random_bytes(8));
    $this->originalRuntimePath = config('platform.runtime.path');
    config(['platform.runtime.path' => $this->runtimePluginPath]);
});

afterEach(function (): void {
    File::deleteDirectory($this->runtimePluginPath);
    config(['platform.runtime.path' => $this->originalRuntimePath]);
});

it('reads empty state without creating absent runtime storage', function (): void {
    $store = app(RuntimePluginStore::class);

    expect($store->readState())->toBe([])
        ->and(is_dir($this->runtimePluginPath))->toBeFalse();
});

it('rejects a symlinked configured runtime root', function (): void {
    $target = sys_get_temp_dir().'/openkos-runtime-root-'.bin2hex(random_bytes(8));
    mkdir($target, 0750, true);
    symlink($target, $this->runtimePluginPath);
    config(['platform.runtime.path' => $this->runtimePluginPath]);
    app()->forgetInstance(RuntimePluginStore::class);

    try {
        expect(fn (): RuntimePluginStore => app(RuntimePluginStore::class))
            ->toThrow(RuntimeException::class, 'symlinked path');
    } finally {
        unlink($this->runtimePluginPath);
        File::deleteDirectory($target);
    }
});

it('restores the previous package after an interrupted swap', function (): void {
    $store = app(RuntimePluginStore::class);
    $activePath = $this->runtimePluginPath.'/acme/recovery';
    $backupPath = $this->runtimePluginPath.'/.backup/acme-recovery-test';

    mkdir($backupPath, 0750, true);
    file_put_contents($backupPath.'/version.txt', 'old');
    mkdir($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/.staging/incoming/version.txt', 'new');
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/acme-recovery-test',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(fn (): null => null);

    expect(file_get_contents($activePath.'/version.txt'))->toBe('old')
        ->and(is_dir($backupPath))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse();
});

it('keeps the new package after a committed swap recovery', function (): void {
    $store = app(RuntimePluginStore::class);
    $activePath = $this->runtimePluginPath.'/acme/recovery';
    $backupPath = $this->runtimePluginPath.'/.backup/acme-recovery-test';

    mkdir($activePath, 0750, true);
    file_put_contents($activePath.'/version.txt', 'new');
    mkdir($backupPath, 0750, true);
    file_put_contents($backupPath.'/version.txt', 'old');
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/acme-recovery-test',
        'had_active' => true,
        'phase' => 'committed',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(fn (): null => null);

    expect(file_get_contents($activePath.'/version.txt'))->toBe('new')
        ->and(is_dir($backupPath))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse();
});

it('finishes an active swap after the new package is promoted', function (): void {
    $store = app(RuntimePluginStore::class);
    $activePath = $this->runtimePluginPath.'/acme/recovery';
    $backupPath = $this->runtimePluginPath.'/.backup/acme-recovery-test';

    mkdir($activePath, 0750, true);
    file_put_contents($activePath.'/version.txt', 'new');
    mkdir($backupPath, 0750, true);
    file_put_contents($backupPath.'/version.txt', 'old');
    file_put_contents($this->runtimePluginPath.'/state.json', json_encode([
        'acme/recovery' => ['enabled' => false],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/acme-recovery-test',
        'had_active' => true,
        'previous_entry' => ['enabled' => false],
        'next_entry' => ['enabled' => true],
        'phase' => 'new_active',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(fn (): null => null);

    expect(file_get_contents($activePath.'/version.txt'))->toBe('new')
        ->and(json_decode(file_get_contents($this->runtimePluginPath.'/state.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toMatchArray(['acme/recovery' => ['enabled' => true]])
        ->and(is_dir($backupPath))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse();
});

it('raises an operational error for corrupted state', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');

    expect(fn (): array => $store->withLock(fn (RuntimePluginStore $store): array => $store->readState()))
        ->toThrow(RuntimeException::class, 'state is corrupted');
});

it('rejects recovery paths outside their managed directories', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => 'state.json',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    expect(fn (): mixed => $store->withLock(fn (): null => null))
        ->toThrow(RuntimeException::class, 'recovery marker is invalid');
});

it('classifies structurally valid but unrecoverable recovery metadata', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    expect($store->recoveryStatus())->toBe(RuntimePluginStore::RECOVERY_UNRECOVERABLE);
});

it('classifies ambiguous prepared recovery metadata as unrecoverable', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath.'/acme/recovery', 0750, true);
    mkdir($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/recovery',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => false,
        'phase' => 'prepared',
    ], JSON_THROW_ON_ERROR));

    expect($store->recoveryStatus())->toBe(RuntimePluginStore::RECOVERY_UNRECOVERABLE);
});

it('classifies stale runtime artifacts without a recovery marker', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath.'/.staging/stale', 0750, true);
    file_put_contents($this->runtimePluginPath.'/.state-stale.tmp', 'stale');

    expect($store->recoveryStatus())->toBe(RuntimePluginStore::RECOVERY_ORPHANED_ARTIFACT);

    $store->withLock(function (RuntimePluginStore $store): void {
        $store->forceCleanupOrphanedArtifacts();
    }, false);

    expect(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/.state-stale.tmp'))->toBeFalse();
});

it('cleans a recovery marker by its own identity', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath.'/.staging/incoming', 0750, true);
    mkdir($this->runtimePluginPath.'/acme/other', 0750, true);
    $store->writeState([
        'acme/missing' => ['enabled' => true],
        'acme/other' => ['enabled' => true],
    ]);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/missing',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(function (RuntimePluginStore $store): void {
        $store->forceCleanupRecovery('acme/missing');
    }, false);

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging/incoming'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/acme/other'))->toBeTrue()
        ->and(app(RuntimePluginStore::class)->readState())->toMatchArray([
            'acme/other' => ['enabled' => true],
        ]);
});

it('does not let malformed recovery paths delete lifecycle state', function (): void {
    $store = app(RuntimePluginStore::class);
    $store->writeState(['acme/other' => ['enabled' => true]]);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/missing',
        'staging' => '.staging/incoming',
        'backup' => 'state.json',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(function (RuntimePluginStore $store): void {
        $store->forceCleanupRecovery('acme/missing');
    }, false);

    expect(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(app(RuntimePluginStore::class)->readState())->toBe([
            'acme/other' => ['enabled' => true],
        ]);
});

it('cleans orphaned lifecycle metadata while holding the store lock', function (): void {
    $store = app(RuntimePluginStore::class);
    mkdir($this->runtimePluginPath.'/.backup/stale', 0750, true);
    mkdir($this->runtimePluginPath.'/.staging/stale', 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', '{broken');
    file_put_contents($this->runtimePluginPath.'/recovery.json', '{broken');
    file_put_contents($this->runtimePluginPath.'/recovery.json.tmp', 'stale');

    $store->withLock(function (RuntimePluginStore $store): void {
        $store->forceCleanupOrphanedMetadata();
    }, false);

    expect(is_file($this->runtimePluginPath.'/state.json'))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeFalse()
        ->and(is_file($this->runtimePluginPath.'/recovery.json.tmp'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.backup'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse();
});

it('surfaces deletion failures instead of reporting force removal success', function (): void {
    $store = app(RuntimePluginStore::class);
    $packagePath = $this->runtimePluginPath.'/acme/failure';
    mkdir(dirname($packagePath), 0750, true);
    mkdir($packagePath, 0750);
    file_put_contents($packagePath.'/locked.txt', 'locked');
    chmod($packagePath, 0500);

    try {
        expect(fn (): mixed => $store->withLock(
            fn (RuntimePluginStore $store): mixed => $store->forceRemove('acme/failure'),
            false,
        ))->toThrow(RuntimeException::class, 'Could not remove runtime plugin path');
    } finally {
        chmod($packagePath, 0700);
    }
});

it('rejects symlinked internal staging paths', function (): void {
    $store = app(RuntimePluginStore::class);
    $outside = sys_get_temp_dir().'/openkos-runtime-outside-'.bin2hex(random_bytes(8));
    mkdir($outside, 0750, true);
    mkdir($this->runtimePluginPath, 0750, true);
    symlink($outside, $this->runtimePluginPath.'/.staging');

    try {
        expect(fn (): mixed => $store->withLock(
            fn (RuntimePluginStore $store): mixed => $store->createStagingPath('incoming'),
            false,
        ))->toThrow(RuntimeException::class, 'contains a symlink');
    } finally {
        unlink($this->runtimePluginPath.'/.staging');
        File::deleteDirectory($outside);
    }
});

it('does not remove a different package while forcing recovery', function (): void {
    $store = app(RuntimePluginStore::class);
    $targetPath = $this->runtimePluginPath.'/acme/target';
    mkdir($targetPath, 0750, true);
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/other',
        'staging' => '.staging/incoming',
        'backup' => '.backup/missing',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    expect(fn (): mixed => $store->withLock(
        fn (RuntimePluginStore $store): mixed => $store->forceRemove('acme/target'),
        false,
    ))->toThrow(RuntimeException::class, 'belongs to a different package');

    expect(is_dir($targetPath))->toBeTrue()
        ->and(is_file($this->runtimePluginPath.'/recovery.json'))->toBeTrue();
});

it('clears recovery artifacts without touching another package', function (): void {
    $store = app(RuntimePluginStore::class);
    $targetPath = $this->runtimePluginPath.'/acme/target';
    $otherPath = $this->runtimePluginPath.'/acme/other';
    mkdir($targetPath, 0750, true);
    mkdir($otherPath, 0750, true);
    mkdir($this->runtimePluginPath.'/.backup/stale', 0750, true);
    mkdir($this->runtimePluginPath.'/.staging/stale', 0750, true);
    file_put_contents($this->runtimePluginPath.'/state.json', json_encode([
        'acme/target' => ['enabled' => false],
        'acme/other' => ['enabled' => true],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($this->runtimePluginPath.'/recovery.json', json_encode([
        'operation' => 'swap',
        'id' => 'acme/target',
        'staging' => '.staging/stale',
        'backup' => '.backup/stale',
        'had_active' => true,
        'phase' => 'old_preserved',
    ], JSON_THROW_ON_ERROR));

    $store->withLock(
        fn (RuntimePluginStore $store): mixed => $store->forceRemove('acme/target'),
        false,
    );

    expect(is_dir($targetPath))->toBeFalse()
        ->and(is_dir($otherPath))->toBeTrue()
        ->and(is_dir($this->runtimePluginPath.'/.backup'))->toBeFalse()
        ->and(is_dir($this->runtimePluginPath.'/.staging'))->toBeFalse()
        ->and(json_decode(file_get_contents($this->runtimePluginPath.'/state.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(['acme/other' => ['enabled' => true]]);
});

it('rejects the application root as runtime plugin storage', function (): void {
    config(['platform.runtime.path' => base_path('.')]);
    app()->forgetInstance(RuntimePluginStore::class);

    expect(fn (): RuntimePluginStore => app(RuntimePluginStore::class))
        ->toThrow(RuntimeException::class, 'dedicated directory');
});
