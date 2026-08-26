<?php

namespace App\Services\Platform;

use Closure;
use RuntimeException;
use Throwable;

final class RuntimePluginStore
{
    private string $root;

    public function __construct()
    {
        $configuredPath = (string) config('platform.runtime.path');
        $path = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? rtrim($configuredPath, DIRECTORY_SEPARATOR)
            : base_path(trim($configuredPath, DIRECTORY_SEPARATOR));
        $this->root = $this->canonicalizePath($path);
        $basePath = realpath(base_path());

        if (
            ! is_string($basePath) ||
            $this->root === DIRECTORY_SEPARATOR ||
            $this->root === $basePath ||
            str_starts_with($basePath, $this->root.DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Runtime plugin storage must be a dedicated directory.');
        }
    }

    public function rootPath(): string
    {
        return $this->root;
    }

    public function withLock(Closure $callback, bool $recover = true): mixed
    {
        $this->ensureDirectory($this->root);

        $handle = fopen($this->root.'/.lock', 'c+');

        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock runtime plugin storage.');
        }

        try {
            if ($recover) {
                $this->recoverPendingOperation();
            }

            return $callback($this);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function recoveryStatus(): string
    {
        $path = $this->recoveryMarkerPath();

        if (is_link($path) || (file_exists($path) && ! is_file($path))) {
            return 'corrupt';
        }

        if (! is_file($path)) {
            return 'clear';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return 'corrupt';
        }

        try {
            $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $this->validateRecoveryMarker($marker);
        } catch (Throwable) {
            return 'corrupt';
        }

        return 'pending';
    }

    /**
     * @return array<string, array{enabled: bool}>
     */
    public function readState(): array
    {
        $path = $this->statePath();

        if (is_link($path)) {
            throw new RuntimeException('Runtime plugin state is corrupted. Repair or remove '.$path.' before continuing.');
        }

        if (file_exists($path) && ! is_file($path)) {
            throw new RuntimeException('Runtime plugin state is corrupted. Repair or remove '.$path.' before continuing.');
        }

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read runtime plugin state.');
        }

        try {
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Runtime plugin state is corrupted. Repair or remove '.$path.' before continuing.',
                previous: $exception,
            );
        }

        if (! is_array($state)) {
            throw new RuntimeException(
                'Runtime plugin state is corrupted. Repair or remove '.$path.' before continuing.',
            );
        }

        foreach ($state as $id => $entry) {
            if (! is_string($id) || ! $this->isValidId($id) || ! is_array($entry) || ! is_bool($entry['enabled'] ?? null)) {
                throw new RuntimeException(
                    'Runtime plugin state is corrupted. Repair or remove '.$path.' before continuing.',
                );
            }
        }

        /** @var array<string, array{enabled: bool}> $state */
        return $state;
    }

    /**
     * @param  array<string, array{enabled: bool}>  $state
     */
    public function writeState(array $state): void
    {
        $this->ensureDirectory($this->root);

        if (is_link($this->statePath())) {
            throw new RuntimeException('Could not persist runtime plugin state through a symlink.');
        }

        try {
            $contents = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not encode runtime plugin state.', previous: $exception);
        }

        $temporaryPath = $this->root.'/.state-'.bin2hex(random_bytes(8)).'.tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $this->statePath())) {
            @unlink($temporaryPath);

            throw new RuntimeException('Could not persist runtime plugin state.');
        }

        @chmod($this->statePath(), 0640);
    }

    /**
     * @return array<string, string>
     */
    public function installedPackages(): array
    {
        $packages = [];

        if (! is_dir($this->root)) {
            return $packages;
        }

        foreach (scandir($this->root) ?: [] as $vendor) {
            if ($vendor === '.' || $vendor === '..' || str_starts_with($vendor, '.')) {
                continue;
            }

            $vendorPath = $this->root.DIRECTORY_SEPARATOR.$vendor;

            if (! is_dir($vendorPath) || is_link($vendorPath)) {
                continue;
            }

            foreach (scandir($vendorPath) ?: [] as $package) {
                if ($package === '.' || $package === '..' || str_starts_with($package, '.')) {
                    continue;
                }

                $id = $vendor.'/'.$package;
                $packagePath = $vendorPath.DIRECTORY_SEPARATOR.$package;

                if ($this->isValidId($id) && is_dir($packagePath) && ! is_link($packagePath)) {
                    $packages[$id] = $packagePath;
                }
            }
        }

        ksort($packages, SORT_STRING);

        return $packages;
    }

    public function packagePath(string $id): string
    {
        $this->assertValidId($id);

        return $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $id);
    }

    public function createStagingPath(string $id): string
    {
        if (preg_match('/^[a-z0-9._-]+$/', $id) !== 1) {
            throw new RuntimeException("Invalid runtime plugin staging label [{$id}].");
        }

        $path = $this->root.'/.staging/'.str_replace('/', '-', $id).'-'.bin2hex(random_bytes(8));
        $this->ensureDirectory($path);

        return $path;
    }

    public function discardStaging(string $path): void
    {
        $relativePath = $this->relativeManagedPath($path);

        if (! str_starts_with($relativePath, '.staging/')) {
            throw new RuntimeException('Only staging paths can be discarded.');
        }

        $this->deleteDirectory($path);
    }

    public function promote(string $id, string $stagingPath, bool $enabled): void
    {
        $this->assertValidId($id);

        $activePath = $this->packagePath($id);
        $relativeStagingPath = $this->relativeManagedPath($stagingPath);
        $backupPath = $this->root.'/.backup/'.str_replace('/', '-', $id).'-'.bin2hex(random_bytes(8));
        $relativeBackupPath = $this->relativeManagedPath($backupPath);
        $hadActivePackage = is_dir($activePath) && ! is_link($activePath);
        $state = $this->readState();
        $marker = [
            'operation' => 'swap',
            'id' => $id,
            'staging' => $relativeStagingPath,
            'backup' => $relativeBackupPath,
            'had_active' => $hadActivePackage,
            'previous_entry' => $state[$id] ?? null,
            'next_entry' => ['enabled' => $enabled],
            'phase' => 'prepared',
        ];

        $this->writeRecoveryMarker($marker);

        try {
            if ($hadActivePackage) {
                $this->ensureDirectory(dirname($backupPath));
                $this->renameOrFail($activePath, $backupPath);
                $marker['phase'] = 'old_preserved';
                $this->writeRecoveryMarker($marker);
            }

            $this->ensureDirectory(dirname($activePath));
            $this->renameOrFail($stagingPath, $activePath);
            $marker['phase'] = 'new_active';
            $this->writeRecoveryMarker($marker);

            $state[$id] = ['enabled' => $enabled];
            $this->writeState($state);
            $marker['phase'] = 'committed';
            $this->writeRecoveryMarker($marker);

            $this->deleteDirectory($backupPath);
            $this->deleteDirectory($this->root.'/.staging');
            $this->removeRecoveryMarker();
        } catch (Throwable $exception) {
            try {
                $this->recoverPendingOperation();
            } catch (Throwable $recoveryException) {
                throw new RuntimeException(
                    'Runtime plugin installation failed and automatic recovery also failed.',
                    previous: $recoveryException,
                );
            }

            throw $exception;
        }
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $this->assertValidId($id);

        if (! is_dir($this->packagePath($id))) {
            throw new RuntimeException("Runtime plugin [{$id}] is not installed.");
        }

        $state = $this->readState();
        $state[$id] = ['enabled' => $enabled];
        $this->writeState($state);
    }

    public function remove(string $id): void
    {
        $this->assertValidId($id);

        $activePath = $this->packagePath($id);
        $state = $this->readState();

        if (! is_dir($activePath) || is_link($activePath)) {
            $this->deleteDirectory($activePath);
            unset($state[$id]);
            $this->writeState($state);

            return;
        }

        $backupPath = $this->root.'/.backup/'.str_replace('/', '-', $id).'-remove-'.bin2hex(random_bytes(8));
        $marker = [
            'operation' => 'remove',
            'id' => $id,
            'staging' => null,
            'backup' => $this->relativeManagedPath($backupPath),
            'had_active' => true,
            'phase' => 'prepared',
        ];

        $this->writeRecoveryMarker($marker);

        try {
            $this->ensureDirectory(dirname($backupPath));
            $this->renameOrFail($activePath, $backupPath);
            $marker['phase'] = 'old_preserved';
            $this->writeRecoveryMarker($marker);

            unset($state[$id]);
            $this->writeState($state);
            $marker['phase'] = 'committed';
            $this->writeRecoveryMarker($marker);

            $this->deleteDirectory($backupPath);
            $this->removeRecoveryMarker();
        } catch (Throwable $exception) {
            $this->recoverPendingOperation();

            throw $exception;
        }
    }

    public function forceRemove(string $id, bool $allowPendingRecovery = false): void
    {
        $this->assertValidId($id);
        $recoveryStatus = $this->recoveryStatus();

        if ($recoveryStatus === 'pending') {
            if (! $allowPendingRecovery) {
                throw new RuntimeException('Runtime plugin recovery is still pending. Complete recovery before forcing removal.');
            }

            $contents = file_get_contents($this->recoveryMarkerPath());
            $marker = is_string($contents) ? json_decode($contents, true) : null;

            $this->validateRecoveryMarker($marker);

            if ($marker['id'] !== $id) {
                throw new RuntimeException('Runtime plugin recovery belongs to a different package.');
            }

            $this->deleteDirectory($this->managedPath($marker['backup']));
            $stagingPath = isset($marker['staging']) && is_string($marker['staging'])
                ? $this->managedPath($marker['staging'])
                : null;
            $this->deleteDirectory($stagingPath);
            $this->removeRecoveryMarker();
        }

        $this->deleteDirectory($this->packagePath($id));

        try {
            $state = $this->readState();
            unset($state[$id]);
            $this->writeState($state);
        } catch (Throwable) {
            // The managed package is still removable when lifecycle metadata is corrupt.
        }

        if ($recoveryStatus === 'corrupt') {
            $this->removeRecoveryMarker();
        }
    }

    public function recoverPendingOperation(): void
    {
        $path = $this->recoveryMarkerPath();

        if (is_link($path)) {
            throw new RuntimeException('Runtime plugin recovery marker is unsafe.');
        }

        if (! is_file($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Runtime plugin recovery marker cannot be read.');
        }

        try {
            $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Runtime plugin recovery marker is corrupted.', previous: $exception);
        }

        $this->validateRecoveryMarker($marker);

        $id = $marker['id'];
        $backupPath = $this->managedPath($marker['backup']);
        $stagingPath = isset($marker['staging']) && is_string($marker['staging'])
            ? $this->managedPath($marker['staging'])
            : null;
        $activePath = $this->packagePath($id);

        if ($marker['phase'] === 'committed') {
            if (! is_dir($activePath)) {
                if (! is_dir($backupPath)) {
                    throw new RuntimeException("Runtime plugin [{$id}] cannot be recovered: active package is missing.");
                }

                $this->restorePreviousState($marker);
                $this->ensureDirectory(dirname($activePath));
                $this->renameOrFail($backupPath, $activePath);
            }

            $this->deleteDirectory($backupPath);
            $this->deleteDirectory($stagingPath);
            $this->removeRecoveryMarker();

            return;
        }

        if ($marker['phase'] === 'prepared') {
            if (($marker['had_active'] ?? false) && ! is_dir($activePath) && is_dir($backupPath)) {
                $this->ensureDirectory(dirname($activePath));
                $this->renameOrFail($backupPath, $activePath);
            } elseif (! ($marker['had_active'] ?? false) && is_dir($activePath)) {
                $this->deleteDirectory($activePath);
            }

            $this->deleteDirectory($stagingPath);
            $this->deleteDirectory($backupPath);
            $this->removeRecoveryMarker();

            return;
        }

        if ($marker['phase'] === 'new_active' && is_dir($activePath)) {
            $state = $this->readState();
            $nextEntry = $marker['next_entry'] ?? null;

            if (! is_array($nextEntry) || ! is_bool($nextEntry['enabled'] ?? null)) {
                throw new RuntimeException('Runtime plugin recovery marker has invalid next state.');
            }

            $state[$id] = ['enabled' => $nextEntry['enabled']];
            $this->writeState($state);
            $this->deleteDirectory($backupPath);
            $this->deleteDirectory($stagingPath);
            $this->removeRecoveryMarker();

            return;
        }

        if (! is_dir($backupPath)) {
            throw new RuntimeException("Runtime plugin [{$id}] cannot be recovered: backup is missing.");
        }

        if (is_dir($activePath)) {
            $this->deleteDirectory($activePath);
        }

        $this->restorePreviousState($marker);
        $this->ensureDirectory(dirname($activePath));
        $this->renameOrFail($backupPath, $activePath);
        $this->deleteDirectory($stagingPath);
        $this->removeRecoveryMarker();
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    private function restorePreviousState(array $marker): void
    {
        $id = $marker['id'];
        $state = $this->readState();
        $previousEntry = $marker['previous_entry'] ?? null;

        if (is_array($previousEntry) && is_bool($previousEntry['enabled'] ?? null)) {
            $state[$id] = ['enabled' => $previousEntry['enabled']];
        } else {
            unset($state[$id]);
        }

        $this->writeState($state);
    }

    private function validateRecoveryMarker(mixed $marker): void
    {
        if (
            ! is_array($marker) ||
            ! in_array($marker['operation'] ?? null, ['swap', 'remove'], true) ||
            ! in_array($marker['phase'] ?? null, ['prepared', 'old_preserved', 'new_active', 'committed'], true) ||
            ! is_string($marker['id'] ?? null) ||
            ! is_string($marker['backup'] ?? null) ||
            ! is_bool($marker['had_active'] ?? null) ||
            ! str_starts_with($marker['backup'], '.backup/') ||
            (isset($marker['staging']) && $marker['staging'] !== null && (! is_string($marker['staging']) || ! str_starts_with($marker['staging'], '.staging/')))
        ) {
            throw new RuntimeException('Runtime plugin recovery marker is invalid.');
        }

        $this->assertValidId($marker['id']);
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    private function writeRecoveryMarker(array $marker): void
    {
        if (is_link($this->recoveryMarkerPath())) {
            throw new RuntimeException('Could not persist runtime plugin recovery state through a symlink.');
        }

        $contents = json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $temporaryPath = $this->recoveryMarkerPath().'.tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $this->recoveryMarkerPath())) {
            @unlink($temporaryPath);

            throw new RuntimeException('Could not persist runtime plugin recovery state.');
        }
    }

    private function removeRecoveryMarker(): void
    {
        @unlink($this->recoveryMarkerPath());
    }

    private function statePath(): string
    {
        return $this->root.'/state.json';
    }

    private function recoveryMarkerPath(): string
    {
        return $this->root.'/recovery.json';
    }

    private function relativeManagedPath(string $path): string
    {
        $prefix = $this->root.DIRECTORY_SEPARATOR;

        if (! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Runtime plugin path is outside the managed storage directory.');
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
    }

    private function managedPath(string $relativePath): string
    {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('Runtime plugin recovery path is unsafe.');
        }

        $path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! str_starts_with($path, $this->root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Runtime plugin recovery path is outside managed storage.');
        }

        return $path;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create runtime plugin directory [{$path}].");
        }
    }

    private function deleteDirectory(?string $path): void
    {
        if ($path === null || (! file_exists($path) && ! is_link($path))) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->deleteDirectory($path.DIRECTORY_SEPARATOR.$entry);
        }

        rmdir($path);
    }

    private function renameOrFail(string $from, string $to): void
    {
        if (! rename($from, $to)) {
            throw new RuntimeException("Could not move runtime plugin path from [{$from}] to [{$to}].");
        }
    }

    private function assertValidId(string $id): void
    {
        if (! $this->isValidId($id)) {
            throw new RuntimeException("Invalid runtime plugin ID [{$id}].");
        }
    }

    private function isValidId(string $id): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $id) === 1;
    }

    private function canonicalizePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Runtime plugin storage must be a dedicated directory.');
        }

        $missing = [];
        $current = $path;

        while (! file_exists($current)) {
            $parent = dirname($current);

            if ($parent === $current) {
                throw new RuntimeException('Runtime plugin storage path cannot be resolved.');
            }

            array_unshift($missing, basename($current));
            $current = $parent;
        }

        $resolved = realpath($current);

        if (! is_string($resolved)) {
            throw new RuntimeException('Runtime plugin storage path cannot be resolved.');
        }

        foreach ($missing as $segment) {
            $resolved .= DIRECTORY_SEPARATOR.$segment;
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }
}
