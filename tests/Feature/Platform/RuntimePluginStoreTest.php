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

it('rejects the application root as runtime plugin storage', function (): void {
    config(['platform.runtime.path' => base_path('.')]);
    app()->forgetInstance(RuntimePluginStore::class);

    expect(fn (): RuntimePluginStore => app(RuntimePluginStore::class))
        ->toThrow(RuntimeException::class, 'dedicated directory');
});
