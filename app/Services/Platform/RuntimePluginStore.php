<?php

namespace App\Services\Platform;

use Closure;
use RuntimeException;
use Throwable;

final class RuntimePluginStore
{
    public const RECOVERY_HEALTHY = 'healthy';

    public const RECOVERY_PENDING = 'pending_recovery';

    public const RECOVERY_UNRECOVERABLE = 'unrecoverable_recovery';

    public const RECOVERY_ORPHANED_ARTIFACT = 'orphaned_runtime_artifact';

    private string $root;

    public function __construct()
    {
        $configuredPath = (string) config('platform.runtime.path');
        $path = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? rtrim($configuredPath, DIRECTORY_SEPARATOR)
            : base_path(trim($configuredPath, DIRECTORY_SEPARATOR));
        $this->assertConfiguredPathHasNoSymlink($path);
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

    public function withLock(Closure $callback, bool $recover = true, ?string $pluginId = null): mixed
    {
        $this->ensureDirectory($this->root);

        $lockPath = $this->root.'/.lock';
        if (is_link($lockPath) || (file_exists($lockPath) && ! is_file($lockPath))) {
            throw new RuntimeException('Runtime plugin lock path is not a regular file.');
        }
        $this->assertSafeManagedPath($lockPath);
        $handle = fopen($lockPath, 'c+');

        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Could not lock runtime plugin storage.');
        }

        try {
            if ($recover) {
                if ($pluginId === null) {
                    $this->recoverPendingOperation();
                } elseif ($this->recoveryMarkerPathForId($pluginId) !== null) {
                    $this->recoverPendingOperation($pluginId);
                }
            }

            return $callback($this);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function recoveryStatus(): string
    {
        try {
            $hasPending = false;

            foreach ($this->recoveryMarkerPaths() as $path) {
                $status = $this->recoveryStatusForPath($path);

                if ($status === self::RECOVERY_UNRECOVERABLE) {
                    return $status;
                }

                $hasPending = $hasPending || $status === self::RECOVERY_PENDING;
            }
        } catch (Throwable) {
            return self::RECOVERY_UNRECOVERABLE;
        }

        if ($hasPending) {
            return self::RECOVERY_PENDING;
        }

        return $this->orphanedRuntimeArtifactPaths() === []
            ? self::RECOVERY_HEALTHY
            : self::RECOVERY_ORPHANED_ARTIFACT;
    }

    public function recoveryIdentity(): ?string
    {
        $identities = $this->recoveryIdentities();

        return count($identities) === 1 ? $identities[0] : null;
    }

    /**
     * @return array<int, string>
     */
    public function recoveryIdentities(): array
    {
        $identities = [];

        foreach ($this->recoveryMarkerPaths() as $path) {
            $contents = @file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            try {
                $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            $id = $path === $this->recoveryMarkerPath()
                ? (is_array($marker) ? $marker['id'] ?? null : null)
                : $this->recoveryIdentityFromPath($path);

            if (is_string($id) && $this->isValidId($id)) {
                $identities[$id] = true;
            }
        }

        return array_keys($identities);
    }

    public function hasRecoveryFor(string $id): bool
    {
        return $this->recoveryMarkerPathForId($id) !== null;
    }

    /**
     * @return array<int, array{id: string|null, status: string, cleanup_key: string}>
     */
    public function recoveryRecords(): array
    {
        $records = [];

        try {
            $paths = $this->recoveryMarkerPaths();
        } catch (Throwable) {
            return [[
                'id' => null,
                'status' => self::RECOVERY_UNRECOVERABLE,
                'cleanup_key' => 'orphaned-recovery',
            ]];
        }

        foreach ($paths as $path) {
            $id = $path === $this->recoveryMarkerPath()
                ? $this->markerIdentity($path)
                : $this->recoveryIdentityFromPath($path);

            try {
                $status = $this->recoveryStatusForPath($path);
            } catch (Throwable) {
                $status = self::RECOVERY_UNRECOVERABLE;
            }

            $records[] = [
                'id' => $id,
                'status' => $status,
                'cleanup_key' => $id === null ? 'orphaned-recovery' : 'recovery:'.$id,
            ];
        }

        return $records;
    }

    /**
     * @return array<string, array{enabled: bool}>
     */
    public function readState(): array
    {
        if (is_link($this->root) || (file_exists($this->root) && ! is_dir($this->root))) {
            throw new RuntimeException('Runtime plugin storage is not a safe directory.');
        }

        if (! is_dir($this->root)) {
            return [];
        }

        $path = $this->statePath();
        $this->assertSafeManagedPath($path);

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
        $statePath = $this->statePath();
        $this->assertSafeManagedPath($statePath);

        if (file_exists($statePath) && ! is_file($statePath)) {
            throw new RuntimeException('Could not persist runtime plugin state through a non-file path.');
        }

        try {
            $contents = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not encode runtime plugin state.', previous: $exception);
        }

        $temporaryPath = $this->root.'/.state-'.bin2hex(random_bytes(8)).'.tmp';
        $this->assertSafeManagedPath($temporaryPath);

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $statePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Could not persist runtime plugin state.');
        }

        @chmod($statePath, 0640);
    }

    /**
     * @return array<string, string>
     */
    public function managedPackageEntries(): array
    {
        $entries = [];

        if (is_link($this->root) || (file_exists($this->root) && ! is_dir($this->root))) {
            throw new RuntimeException('Runtime plugin storage is not a safe directory.');
        }

        if (! is_dir($this->root)) {
            return $entries;
        }

        $this->assertSafeManagedPath($this->root);

        $vendors = scandir($this->root);

        if ($vendors === false) {
            throw new RuntimeException('Could not inspect runtime plugin storage.');
        }

        foreach ($vendors as $vendor) {
            if ($vendor === '.' || $vendor === '..' || str_starts_with($vendor, '.')) {
                continue;
            }

            $vendorPath = $this->root.DIRECTORY_SEPARATOR.$vendor;

            if (! is_dir($vendorPath) || is_link($vendorPath)) {
                continue;
            }

            $packages = scandir($vendorPath);

            if ($packages === false) {
                continue;
            }

            foreach ($packages as $package) {
                if ($package === '.' || $package === '..' || str_starts_with($package, '.')) {
                    continue;
                }

                $id = $vendor.'/'.$package;
                $packagePath = $vendorPath.DIRECTORY_SEPARATOR.$package;

                if ($this->isValidId($id) && (file_exists($packagePath) || is_link($packagePath))) {
                    $entries[$id] = $packagePath;
                }
            }
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    public function installedPackages(): array
    {
        $packages = [];

        foreach ($this->managedPackageEntries() as $id => $path) {
            if (! $this->isRealDirectory($path)) {
                continue;
            }

            $this->assertSafeManagedPath($path);
            $packages[$id] = $path;
        }

        return $packages;
    }

    /**
     * @return array<string, string>
     */
    public function managedFilesystemAnomalies(): array
    {
        $anomalies = [];

        if (is_link($this->root) || (file_exists($this->root) && ! is_dir($this->root))) {
            throw new RuntimeException('Runtime plugin storage is not a safe directory.');
        }

        if (! is_dir($this->root)) {
            return $anomalies;
        }

        $this->assertSafeManagedPath($this->root);
        $vendors = scandir($this->root);

        if ($vendors === false) {
            throw new RuntimeException('Could not inspect runtime plugin storage.');
        }

        $expectedFiles = ['.lock', 'state.json', 'recovery.json', 'recovery.json.tmp'];
        $expectedDirectories = ['.staging', '.backup', '.recovery'];

        foreach ($vendors as $vendor) {
            if ($vendor === '.' || $vendor === '..') {
                continue;
            }

            $vendorPath = $this->root.'/'.$vendor;

            if (in_array($vendor, $expectedFiles, true)) {
                if (! $this->isRegularFile($vendorPath)) {
                    $anomalies['internal:'.$vendor] = $vendorPath;
                }

                continue;
            }

            if (in_array($vendor, $expectedDirectories, true)) {
                if (! $this->isRealDirectory($vendorPath)) {
                    $anomalies['internal:'.$vendor] = $vendorPath;

                    continue;
                }

                if ($vendor === '.recovery') {
                    $recoveryEntries = @scandir($vendorPath);

                    if ($recoveryEntries === false) {
                        $anomalies['internal:.recovery'] = $vendorPath;

                        continue;
                    }

                    foreach ($recoveryEntries as $recoveryEntry) {
                        if ($recoveryEntry === '.' || $recoveryEntry === '..') {
                            continue;
                        }

                        $recoveryEntryPath = $vendorPath.'/'.$recoveryEntry;

                        if (
                            ! str_ends_with($recoveryEntry, '.json')
                            || ! $this->isRegularFile($recoveryEntryPath)
                            || $this->recoveryIdentityFromPath($recoveryEntryPath) === null
                        ) {
                            $anomalies['internal:.recovery/'.$recoveryEntry] = $recoveryEntryPath;
                        }
                    }
                }

                continue;
            }

            if (str_starts_with($vendor, '.state-') && str_ends_with($vendor, '.tmp')) {
                if (is_link($vendorPath)) {
                    $anomalies['internal:'.$vendor] = $vendorPath;
                }

                continue;
            }

            if (str_starts_with($vendor, '.')) {
                $anomalies['internal:'.$vendor] = $vendorPath;

                continue;
            }

            if (! $this->isRealDirectory($vendorPath)) {
                $anomalies['vendor:'.$vendor] = $vendorPath;

                continue;
            }

            $packages = @scandir($vendorPath);

            if ($packages === false) {
                $anomalies['vendor:'.$vendor] = $vendorPath;

                continue;
            }

            foreach ($packages as $package) {
                if ($package === '.' || $package === '..') {
                    continue;
                }

                $id = $vendor.'/'.$package;
                $packagePath = $vendorPath.'/'.$package;

                if (! $this->isValidId($id)) {
                    $anomalies['path:'.$vendor.'/'.$package] = $packagePath;
                } elseif (! $this->isRealDirectory($packagePath)) {
                    $anomalies['package:'.$id] = $packagePath;
                }
            }
        }

        ksort($anomalies, SORT_STRING);

        return $anomalies;
    }

    /**
     * @return array<string, string>
     */
    public function orphanedRuntimeArtifactPaths(): array
    {
        $artifacts = [];

        if (is_link($this->root) || (file_exists($this->root) && ! is_dir($this->root))) {
            throw new RuntimeException('Runtime plugin storage is not a safe directory.');
        }

        if (! is_dir($this->root)) {
            return $artifacts;
        }

        $this->assertSafeManagedPath($this->root);

        foreach (['.staging', '.backup', 'recovery.json.tmp'] as $name) {
            $path = $this->root.'/'.$name;

            if ((file_exists($path) || is_link($path)) && ! is_link($path)) {
                $artifacts[$name] = $path;
            }
        }

        $entries = scandir($this->root);

        if ($entries === false) {
            throw new RuntimeException('Could not inspect runtime plugin storage.');
        }

        foreach ($entries as $entry) {
            if (! str_starts_with($entry, '.state-') || ! str_ends_with($entry, '.tmp')) {
                continue;
            }

            $path = $this->root.'/'.$entry;

            if ((file_exists($path) || is_link($path)) && ! is_link($path)) {
                $artifacts[$entry] = $path;
            }
        }

        return $artifacts;
    }

    public function forceCleanupOrphanedMetadata(): void
    {
        if ($this->managedPackageEntries() !== []) {
            throw new RuntimeException('Runtime plugin packages must be removed before cleaning orphaned metadata.');
        }

        $statePath = $this->statePath();
        if ($this->managedEntryExists($statePath)) {
            $this->deleteManagedPath($statePath);
        }

        $this->cleanupRecoveryArtifacts();

        if ($this->managedEntryExists($statePath)) {
            throw new RuntimeException("Could not clean runtime lifecycle path [{$statePath}].");
        }
    }

    public function forceCleanupOrphanedArtifacts(): void
    {
        if ($this->recoveryMarkerExists()) {
            throw new RuntimeException('Runtime plugin recovery metadata must be handled before cleaning orphaned artifacts.');
        }

        foreach ($this->orphanedRuntimeArtifactPaths() as $path) {
            $this->assertSafeManagedPath($path);
            $this->deleteDirectory($path);
        }

        if ($this->orphanedRuntimeArtifactPaths() !== []) {
            throw new RuntimeException('Could not clean orphaned runtime artifacts.');
        }
    }

    public function forceCleanupRecovery(string $id): void
    {
        $this->assertValidId($id);
        $markerPath = $this->recoveryMarkerPathForId($id);

        if ($markerPath === null) {
            throw new RuntimeException('Runtime plugin recovery metadata is not available.');
        }

        if (! is_file($markerPath) || is_link($markerPath)) {
            $this->deleteManagedPath($markerPath);
            $this->removeEmptyRecoveryDirectory();

            return;
        }

        $contents = file_get_contents($markerPath);

        if ($contents === false) {
            throw new RuntimeException('Runtime plugin recovery marker cannot be read.');
        }

        try {
            $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $marker = [];
        }

        if (! is_array($marker)) {
            $marker = [];
        }

        if ($marker !== [] && ($marker['id'] ?? null) !== $id) {
            if ($this->recoveryIdentityFromPath($markerPath) !== $id) {
                throw new RuntimeException('Runtime plugin recovery belongs to a different package.');
            }

            $marker = [];
        }

        try {
            $state = $this->readState();
            if (array_key_exists($id, $state)) {
                unset($state[$id]);
                $this->writeState($state);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $paths = [$markerPath, $markerPath.'.tmp'];

        foreach (['backup', 'staging'] as $key) {
            if (! array_key_exists($key, $marker)) {
                continue;
            }

            $path = $this->recoveryCleanupPath($key, $marker[$key]);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        foreach (array_unique($paths) as $path) {
            if (! $this->managedEntryExists($path)) {
                continue;
            }

            $this->deleteManagedPath($path);
        }

        $this->removeEmptyRecoveryDirectory();

        foreach (array_unique($paths) as $path) {
            if ($this->managedEntryExists($path)) {
                throw new RuntimeException("Could not clean runtime lifecycle path [{$path}].");
            }
        }

        foreach ([$this->root.'/.staging', $this->root.'/.backup'] as $container) {
            if (! is_dir($container) || is_link($container)) {
                continue;
            }

            $entries = scandir($container);

            if ($entries === false) {
                throw new RuntimeException("Could not inspect runtime lifecycle path [{$container}].");
            }

            if ($entries === ['.', '..']) {
                $this->assertSafeManagedPath($container);
                $this->deleteDirectory($container);
            }
        }
    }

    public function forceCleanupUnknownRecovery(): void
    {
        if ($this->markerIdentity($this->recoveryMarkerPath()) !== null) {
            throw new RuntimeException('Runtime plugin recovery belongs to a known package.');
        }

        $markerPath = $this->recoveryMarkerPath();

        if (! is_file($markerPath) || is_link($markerPath)) {
            throw new RuntimeException('Runtime plugin recovery metadata is not available.');
        }

        $contents = file_get_contents($markerPath);

        $paths = [$markerPath, $this->root.'/recovery.json.tmp'];

        if ($contents !== false) {
            try {
                $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($marker)) {
                    foreach (['backup', 'staging'] as $key) {
                        $path = $this->recoveryCleanupPath($key, $marker[$key] ?? null);

                        if ($path !== null) {
                            $paths[] = $path;
                        }
                    }
                }
            } catch (Throwable) {
                // The marker itself is the only trustworthy cleanup target.
            }
        }

        foreach (array_unique($paths) as $path) {
            if (! $this->managedEntryExists($path)) {
                continue;
            }

            $this->deleteManagedPath($path);
        }

        foreach (array_unique($paths) as $path) {
            if ($this->managedEntryExists($path)) {
                throw new RuntimeException("Could not clean runtime lifecycle path [{$path}].");
            }
        }

        foreach ([$this->root.'/.staging', $this->root.'/.backup'] as $container) {
            if (! is_dir($container) || is_link($container)) {
                continue;
            }

            $entries = scandir($container);

            if ($entries === false) {
                throw new RuntimeException("Could not inspect runtime lifecycle path [{$container}].");
            }

            if ($entries === ['.', '..']) {
                $this->deleteManagedPath($container);
            }
        }

        if ($this->managedPackageEntries() === []) {
            $statePath = $this->statePath();

            if ($this->managedEntryExists($statePath)) {
                $this->deleteManagedPath($statePath);
            }
        }
    }

    public function forceCleanupFilesystemAnomaly(string $key): void
    {
        $anomalies = $this->managedFilesystemAnomalies();

        if (! isset($anomalies[$key])) {
            throw new RuntimeException('Runtime filesystem anomaly is no longer present.');
        }

        $path = $anomalies[$key];
        $this->deleteManagedPath($path);

        if (str_starts_with($key, 'package:')) {
            try {
                $state = $this->readState();
                unset($state[substr($key, strlen('package:'))]);
                $this->writeState($state);
            } catch (Throwable) {
                // The symlink is gone; a corrupt state file remains independently recoverable.
            }
        }
    }

    private function cleanupRecoveryArtifacts(): void
    {
        if (! is_dir($this->root)) {
            return;
        }

        $this->assertSafeManagedPath($this->root);

        foreach ($this->recoveryArtifactPaths() as $path) {
            if ($this->managedEntryExists($path)) {
                $this->deleteManagedPath($path);
            }
        }

        $entries = scandir($this->root);

        if ($entries === false) {
            throw new RuntimeException('Could not inspect runtime plugin storage.');
        }

        foreach ($entries as $entry) {
            if (
                (! str_starts_with($entry, '.state-') || ! str_ends_with($entry, '.tmp'))
                && $entry !== 'recovery.json.tmp'
            ) {
                continue;
            }

            $path = $this->root.'/'.$entry;
            $this->deleteManagedPath($path);
        }

        foreach ($this->recoveryArtifactPaths() as $path) {
            if ($this->managedEntryExists($path)) {
                throw new RuntimeException("Could not clean runtime lifecycle path [{$path}].");
            }
        }

        $this->removeEmptyRecoveryDirectory();
    }

    private function recoveryMarkerExists(): bool
    {
        return $this->recoveryMarkerPaths() !== [];
    }

    public function packagePath(string $id): string
    {
        $this->assertValidId($id);

        return $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $id);
    }

    public function installedPackagePath(string $id): string
    {
        $path = $this->packagePath($id);
        $this->assertSafeManagedPath($path);

        if (! $this->isRealDirectory($path)) {
            throw new RuntimeException("Runtime plugin [{$id}] is not installed.");
        }

        return $path;
    }

    public function createStagingPath(string $id): string
    {
        if (preg_match('/^[a-z0-9._-]+$/', $id) !== 1) {
            throw new RuntimeException("Invalid runtime plugin staging label [{$id}].");
        }

        $stagingRoot = $this->root.'/.staging';
        $this->assertSafeManagedPath($stagingRoot);
        $path = $stagingRoot.'/'.str_replace('/', '-', $id).'-'.bin2hex(random_bytes(8));
        $this->ensureDirectory($path);
        $this->assertSafeManagedPath($path);

        return $path;
    }

    public function discardStaging(string $path): void
    {
        $relativePath = $this->relativeManagedPath($path);

        if (! str_starts_with($relativePath, '.staging/')) {
            throw new RuntimeException('Only staging paths can be discarded.');
        }

        $this->deleteManagedPath($path);

        $container = $this->root.'/.staging';
        if (! is_dir($container) || is_link($container)) {
            return;
        }

        $entries = scandir($container);

        if ($entries === false) {
            throw new RuntimeException("Could not inspect runtime lifecycle path [{$container}].");
        }

        if ($entries === ['.', '..']) {
            $this->deleteManagedPath($container);
        }
    }

    public function promote(string $id, string $stagingPath, bool $enabled): void
    {
        $this->assertValidId($id);

        $activePath = $this->packagePath($id);
        $relativeStagingPath = $this->relativeManagedPath($stagingPath);
        $backupPath = $this->root.'/.backup/'.str_replace('/', '-', $id).'-'.bin2hex(random_bytes(8));
        $relativeBackupPath = $this->relativeManagedPath($backupPath);
        $this->assertSafeManagedPath($activePath);
        $this->assertSafeManagedPath($stagingPath);
        $this->assertSafeManagedPath(dirname($backupPath));
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
            $this->assertSafeManagedPath(dirname($activePath));
            $this->renameOrFail($stagingPath, $activePath);
            $marker['phase'] = 'new_active';
            $this->writeRecoveryMarker($marker);

            $state[$id] = ['enabled' => $enabled];
            $this->writeState($state);
            $marker['phase'] = 'committed';
            $this->writeRecoveryMarker($marker);

            $this->deleteDirectory($backupPath);
            $this->discardStaging($stagingPath);
            $this->removeRecoveryMarker($id);
        } catch (Throwable $exception) {
            try {
                $this->recoverPendingOperation($id);
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
        $this->installedPackagePath($id);

        $state = $this->readState();
        $state[$id] = ['enabled' => $enabled];
        $this->writeState($state);
    }

    public function remove(string $id): void
    {
        $this->assertValidId($id);

        $activePath = $this->packagePath($id);
        $this->assertSafeManagedPath($activePath);
        $state = $this->readState();
        $recoveryStatus = $this->recoveryStatus();

        if (
            in_array($recoveryStatus, [self::RECOVERY_PENDING, self::RECOVERY_UNRECOVERABLE], true)
            && ! $this->hasRecoveryFor($id)
        ) {
            $this->deleteDirectory($activePath);
            unset($state[$id]);
            $this->writeState($state);

            return;
        }

        if (! is_dir($activePath) || is_link($activePath)) {
            $this->deleteDirectory($activePath);
            unset($state[$id]);
            $this->writeState($state);

            return;
        }

        $backupPath = $this->root.'/.backup/'.str_replace('/', '-', $id).'-remove-'.bin2hex(random_bytes(8));
        $this->assertSafeManagedPath(dirname($backupPath));
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
            $this->removeRecoveryMarker($id);
        } catch (Throwable $exception) {
            $this->recoverPendingOperation($id);

            throw $exception;
        }
    }

    public function forceRemove(string $id, bool $allowPendingRecovery = false): void
    {
        $this->assertValidId($id);
        $activePath = $this->packagePath($id);
        $this->assertSafeManagedPath($activePath);
        $recoveryStatus = $this->recoveryStatus();
        $recoveryPaths = $this->recoveryMarkerPaths();
        $targetRecoveryPath = $this->recoveryMarkerPathForId($id);
        $preserveUnrelatedRecovery = $recoveryPaths !== []
            && ($targetRecoveryPath === null || count($recoveryPaths) > 1);

        if (
            in_array($recoveryStatus, [self::RECOVERY_PENDING, self::RECOVERY_UNRECOVERABLE], true)
            && ! $preserveUnrelatedRecovery
        ) {
            $this->assertRecoveryBelongsTo($id);
        }

        if ($recoveryStatus === self::RECOVERY_PENDING) {
            if (! $preserveUnrelatedRecovery && ! $allowPendingRecovery) {
                throw new RuntimeException('Runtime plugin recovery is still pending. Complete recovery before forcing removal.');
            }

            if (! $preserveUnrelatedRecovery) {
                try {
                    $this->recoverPendingOperation($id);
                    $recoveryStatus = $this->recoveryStatus();
                } catch (Throwable) {
                    $recoveryStatus = self::RECOVERY_UNRECOVERABLE;
                }
            }
        }

        $stateWasCorrupt = false;
        $state = [];
        try {
            $state = $this->readState();
        } catch (Throwable) {
            $stateWasCorrupt = true;
        }

        $this->deleteDirectory($activePath);

        if (! $stateWasCorrupt) {
            unset($state[$id]);
            $this->writeState($state);
        }

        if ($this->recoveryMarkerPathForId($id) !== null) {
            $this->forceCleanupRecovery($id);
        }

        if ($this->managedPackageEntries() === [] && $this->recoveryMarkerPaths() === []) {
            $this->forceCleanupOrphanedMetadata();
        }
    }

    public function recoverPendingOperation(?string $id = null): void
    {
        $path = $id !== null
            ? $this->recoveryMarkerPathForId($id)
            : $this->singleRecoveryMarkerPath();

        if ($path === null) {
            return;
        }

        if (is_link($path)) {
            throw new RuntimeException('Runtime plugin recovery marker is unsafe.');
        }

        if (! is_file($path)) {
            if (file_exists($path) || is_link($path)) {
                throw new RuntimeException('Runtime plugin recovery marker is unsafe.');
            }

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

        $pathIdentity = $this->recoveryIdentityFromPath($path);
        if (($pathIdentity !== null && $marker['id'] !== $pathIdentity) || ($id !== null && $marker['id'] !== $id)) {
            throw new RuntimeException('Runtime plugin recovery belongs to a different package.');
        }

        $id = $marker['id'];
        $backupPath = $this->managedPath($marker['backup']);
        $stagingPath = isset($marker['staging']) && is_string($marker['staging'])
            ? $this->managedPath($marker['staging'])
            : null;
        $activePath = $this->packagePath($id);
        $this->assertSafeManagedPath($backupPath);
        $this->assertSafeManagedPath($stagingPath ?? $this->root);
        $this->assertSafeManagedPath($activePath);

        if ($marker['phase'] === 'committed') {
            if ($marker['operation'] === 'remove') {
                if ($this->managedEntryExists($activePath)) {
                    throw new RuntimeException("Runtime plugin [{$id}] unexpectedly exists after removal was committed.");
                }

                $this->deleteDirectory($backupPath);
                $this->removeRecoveryMarker($id, $path);

                return;
            }

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
            $this->removeRecoveryMarker($id, $path);

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
            $this->removeRecoveryMarker($id, $path);

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
            $this->removeRecoveryMarker($id, $path);

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
        $this->removeRecoveryMarker($id, $path);
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

        if ($marker['operation'] === 'swap' && ! is_string($marker['staging'] ?? null)) {
            throw new RuntimeException('Runtime plugin recovery marker is invalid.');
        }

        if (
            $marker['operation'] === 'remove'
            && (($marker['staging'] ?? null) !== null || $marker['had_active'] !== true)
        ) {
            throw new RuntimeException('Runtime plugin recovery marker is invalid.');
        }

        if (
            $marker['operation'] === 'remove' && $marker['phase'] === 'new_active'
        ) {
            throw new RuntimeException('Runtime plugin recovery marker has an invalid phase transition.');
        }

        if (
            $marker['operation'] === 'swap'
            && $marker['phase'] === 'new_active'
            && (! is_array($marker['next_entry'] ?? null) || ! is_bool($marker['next_entry']['enabled'] ?? null))
        ) {
            throw new RuntimeException('Runtime plugin recovery marker has invalid next state.');
        }
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    private function isRecoveryMarkerRecoverable(array $marker): bool
    {
        try {
            $activePath = $this->packagePath($marker['id']);
            $backupPath = $this->managedPath($marker['backup']);
            $stagingPath = isset($marker['staging']) && is_string($marker['staging'])
                ? $this->managedPath($marker['staging'])
                : null;
            $this->assertSafeManagedPath($activePath);
            $this->assertSafeManagedPath($backupPath);
            $this->assertSafeManagedPath($stagingPath ?? $this->root);
        } catch (Throwable) {
            return false;
        }

        $activeExists = is_dir($activePath) && ! is_link($activePath);
        $backupExists = is_dir($backupPath) && ! is_link($backupPath);
        $stagingExists = $stagingPath !== null && is_dir($stagingPath) && ! is_link($stagingPath);

        if ($marker['operation'] === 'swap') {
            return match ($marker['phase']) {
                'prepared' => $stagingExists && ($marker['had_active']
                    ? ($activeExists xor $backupExists)
                    : ! $activeExists && ! $backupExists),
                'old_preserved' => $backupExists && $stagingExists && ! $activeExists && $this->stateIsReadable(),
                'new_active' => $activeExists
                    && ($marker['had_active'] ? $backupExists : ! $backupExists)
                    && $this->stateIsReadable(),
                'committed' => $marker['had_active']
                    ? ($activeExists || ($backupExists && $this->stateIsReadable()))
                    : $activeExists,
                default => false,
            };
        }

        return match ($marker['phase']) {
            'prepared' => $activeExists xor $backupExists,
            'old_preserved' => $backupExists && ! $activeExists && $this->stateIsReadable(),
            'committed' => ! $activeExists && $backupExists && $this->stateIsReadable(),
            default => false,
        };
    }

    private function stateIsReadable(): bool
    {
        try {
            $this->readState();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function assertRecoveryBelongsTo(string $id): void
    {
        if ($this->recoveryMarkerPathForId($id) !== null) {
            return;
        }

        throw new RuntimeException('Runtime plugin recovery belongs to a different package.');
    }

    /**
     * @param  array<string, mixed>  $marker
     */
    private function writeRecoveryMarker(array $marker): void
    {
        $recoveryPath = $this->recoveryMarkerPathForWrite($marker['id']);
        $this->assertSafeManagedPath($recoveryPath);

        if (file_exists($recoveryPath) && ! is_file($recoveryPath)) {
            throw new RuntimeException('Could not persist runtime plugin recovery state through a non-file path.');
        }

        $contents = json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $temporaryPath = $recoveryPath.'.tmp';
        if (is_link($temporaryPath) || (file_exists($temporaryPath) && ! is_file($temporaryPath))) {
            throw new RuntimeException('Could not persist runtime plugin recovery state through a non-file path.');
        }
        $this->assertSafeManagedPath($temporaryPath);

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $recoveryPath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Could not persist runtime plugin recovery state.');
        }
    }

    private function removeRecoveryMarker(?string $id = null, ?string $path = null): void
    {
        $path ??= $id !== null
            ? $this->recoveryMarkerPathForId($id)
            : $this->recoveryMarkerPath();

        if ($path === null || ! $this->managedEntryExists($path)) {
            return;
        }

        $this->deleteManagedPath($path);
        $this->removeEmptyRecoveryDirectory();
    }

    /** @return array<int, string> */
    private function recoveryArtifactPaths(): array
    {
        $markers = $this->recoveryMarkerPaths();

        return [
            ...$markers,
            ...array_map(fn (string $path): string => $path.'.tmp', $markers),
            $this->root.'/.staging',
            $this->root.'/.backup',
            $this->root.'/recovery.json.tmp',
        ];
    }

    private function statePath(): string
    {
        return $this->root.'/state.json';
    }

    private function recoveryMarkerPath(): string
    {
        return $this->root.'/recovery.json';
    }

    private function recoveryMarkerPathForWrite(string $id): string
    {
        $rootPath = $this->recoveryMarkerPath();

        if (! $this->managedEntryExists($rootPath) || $this->markerIdentity($rootPath) === $id) {
            return $rootPath;
        }

        $directory = $this->root.'/.recovery';
        $this->ensureDirectory($directory);

        return $this->recoveryMarkerPathForId($id, false);
    }

    private function recoveryMarkerPathForId(string $id, bool $allowMissing = true): ?string
    {
        $this->assertValidId($id);
        $sidecar = $this->root.'/.recovery/'.bin2hex($id).'.json';

        if ($this->managedEntryExists($sidecar)) {
            return $sidecar;
        }

        $directory = $this->root.'/.recovery';

        if ($this->managedEntryExists($directory) && is_dir($directory) && ! is_link($directory)) {
            $entries = scandir($directory);

            if ($entries === false) {
                throw new RuntimeException("Could not inspect runtime lifecycle path [{$directory}].");
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || ! str_ends_with($entry, '.json')) {
                    continue;
                }

                $path = $directory.'/'.$entry;

                if ($this->recoveryIdentityFromPath($path) === $id && $this->managedEntryExists($path)) {
                    return $path;
                }
            }
        }

        $rootPath = $this->recoveryMarkerPath();

        if ($this->managedEntryExists($rootPath) && $this->markerIdentity($rootPath) === $id) {
            return $rootPath;
        }

        return $allowMissing ? null : $sidecar;
    }

    /**
     * @return array<int, string>
     */
    private function recoveryMarkerPaths(): array
    {
        $paths = [];
        $rootPath = $this->recoveryMarkerPath();

        if ($this->managedEntryExists($rootPath)) {
            $paths[] = $rootPath;
        }

        $directory = $this->root.'/.recovery';

        if (! $this->managedEntryExists($directory)) {
            return $paths;
        }

        if (is_link($directory) || ! is_dir($directory)) {
            $paths[] = $directory;

            return $paths;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            $paths[] = $directory;

            return $paths;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || ! str_ends_with($entry, '.json')) {
                continue;
            }

            $path = $directory.'/'.$entry;

            if ($this->managedEntryExists($path)) {
                $paths[] = $path;
            }
        }

        sort($paths, SORT_STRING);

        return $paths;
    }

    private function singleRecoveryMarkerPath(): ?string
    {
        $paths = $this->recoveryMarkerPaths();

        if (count($paths) > 1) {
            throw new RuntimeException('Multiple runtime plugin recovery records require a plugin identity.');
        }

        return $paths[0] ?? null;
    }

    private function recoveryStatusForPath(string $path): string
    {
        if (is_link($path) || ! is_file($path)) {
            return self::RECOVERY_UNRECOVERABLE;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return self::RECOVERY_UNRECOVERABLE;
        }

        try {
            $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $this->validateRecoveryMarker($marker);

            $pathIdentity = $this->recoveryIdentityFromPath($path);
            if ($pathIdentity !== null && $marker['id'] !== $pathIdentity) {
                return self::RECOVERY_UNRECOVERABLE;
            }
        } catch (Throwable) {
            return self::RECOVERY_UNRECOVERABLE;
        }

        return $this->isRecoveryMarkerRecoverable($marker)
            ? self::RECOVERY_PENDING
            : self::RECOVERY_UNRECOVERABLE;
    }

    private function markerIdentity(string $path): ?string
    {
        if (! is_file($path) || is_link($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $id = is_array($marker) ? $marker['id'] ?? null : null;

        return is_string($id) && $this->isValidId($id) ? $id : null;
    }

    private function recoveryIdentityFromPath(string $path): ?string
    {
        $prefix = $this->root.'/.recovery/';

        if (! str_starts_with($path, $prefix) || ! str_ends_with($path, '.json')) {
            return null;
        }

        $encoded = substr($path, strlen($prefix), -5);
        $decoded = ctype_xdigit($encoded) ? hex2bin($encoded) : false;

        return is_string($decoded) && $this->isValidId($decoded) ? $decoded : null;
    }

    private function removeEmptyRecoveryDirectory(): void
    {
        $directory = $this->root.'/.recovery';

        if (! $this->managedEntryExists($directory) || is_link($directory) || ! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException("Could not inspect runtime lifecycle path [{$directory}].");
        }

        if ($entries === ['.', '..']) {
            $this->deleteManagedPath($directory);
        }
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
        $segments = explode('/', $relativePath);

        if (
            $relativePath === ''
            || str_contains($relativePath, '..')
            || str_starts_with($relativePath, '/')
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
        ) {
            throw new RuntimeException('Runtime plugin recovery path is unsafe.');
        }

        $path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! str_starts_with($path, $this->root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Runtime plugin recovery path is outside managed storage.');
        }

        return $path;
    }

    private function recoveryCleanupPath(string $key, mixed $relativePath): ?string
    {
        $prefix = $key === 'backup' ? '.backup/' : '.staging/';

        if (! is_string($relativePath) || ! str_starts_with($relativePath, $prefix) || $relativePath === $prefix) {
            return null;
        }

        try {
            return $this->managedPath($relativePath);
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            throw new RuntimeException("Runtime plugin path is not a safe directory [{$path}].");
        }

        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create runtime plugin directory [{$path}].");
        }
    }

    private function deleteDirectory(?string $path): void
    {
        if ($path === null || ! $this->managedEntryExists($path)) {
            return;
        }

        if (is_link($path)) {
            $this->assertSafeManagedLink($path);

            if (! @unlink($path) || $this->managedEntryExists($path)) {
                throw new RuntimeException("Could not remove runtime plugin path [{$path}].");
            }

            return;
        }

        if (! is_dir($path)) {
            $this->assertSafeManagedPath($path);

            if (! @unlink($path) || $this->managedEntryExists($path)) {
                throw new RuntimeException("Could not remove runtime plugin path [{$path}].");
            }

            return;
        }

        $this->assertSafeManagedPath($path);

        $entries = @scandir($path);
        if ($entries === false) {
            throw new RuntimeException("Could not inspect runtime plugin path [{$path}].");
        }

        $this->assertSafeManagedPath($path);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->deleteDirectory($path.DIRECTORY_SEPARATOR.$entry);
        }

        $this->assertSafeManagedPath($path);

        if (! @rmdir($path) || $this->managedEntryExists($path)) {
            throw new RuntimeException("Could not remove runtime plugin path [{$path}].");
        }
    }

    private function managedEntryExists(string $path): bool
    {
        if (@lstat($path) !== false) {
            return true;
        }

        $current = dirname($path);
        $missingEntry = basename($path);

        while (true) {
            $stat = @lstat($current);

            if ($stat !== false) {
                if (is_link($current) || ! is_dir($current)) {
                    throw new RuntimeException("Could not inspect runtime plugin path [{$current}].");
                }

                $entries = @scandir($current);

                if ($entries === false) {
                    throw new RuntimeException("Could not inspect runtime plugin path [{$current}].");
                }

                if (! in_array($missingEntry, $entries, true)) {
                    return false;
                }

                throw new RuntimeException("Could not inspect runtime plugin path [{$path}].");
            }

            $parent = dirname($current);

            if ($parent === $current) {
                throw new RuntimeException("Could not inspect runtime plugin path [{$path}].");
            }

            $missingEntry = basename($current);
            $current = $parent;
        }
    }

    private function deleteManagedPath(string $path): void
    {
        if (is_link($path)) {
            $this->assertSafeManagedLink($path);
        } else {
            $this->assertSafeManagedPath($path);
        }

        $this->deleteDirectory($path);
    }

    private function assertSafeManagedPath(string $path): void
    {
        if ($path !== $this->root && ! str_starts_with($path, $this->root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Runtime plugin path is outside managed storage.');
        }

        $resolvedRoot = realpath($this->root);
        if (! is_string($resolvedRoot) || $resolvedRoot !== $this->root) {
            throw new RuntimeException('Runtime plugin storage path cannot be resolved.');
        }

        $current = $path;
        while (! file_exists($current) && ! is_link($current)) {
            $parent = dirname($current);

            if ($parent === $current) {
                throw new RuntimeException('Runtime plugin path cannot be resolved.');
            }

            $current = $parent;
        }

        if (is_link($current)) {
            throw new RuntimeException("Runtime plugin path contains a symlink [{$current}].");
        }

        $resolved = realpath($current);
        if (
            ! is_string($resolved)
            || ($resolved !== $resolvedRoot && ! str_starts_with($resolved, $resolvedRoot.DIRECTORY_SEPARATOR))
        ) {
            throw new RuntimeException('Runtime plugin path is outside managed storage.');
        }
    }

    private function assertSafeManagedLink(string $path): void
    {
        if (! is_link($path)) {
            throw new RuntimeException("Runtime plugin path is no longer a symlink [{$path}].");
        }

        if ($path !== $this->root && ! str_starts_with($path, $this->root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Runtime plugin path is outside managed storage.');
        }

        $this->assertSafeManagedPath(dirname($path));
    }

    private function assertConfiguredPathHasNoSymlink(string $path): void
    {
        $current = $path;

        while (true) {
            if (is_link($current)) {
                throw new RuntimeException('Runtime plugin storage cannot use a symlinked path.');
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return;
            }

            $current = $parent;
        }
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

    private function isRegularFile(string $path): bool
    {
        return is_file($path) && ! is_link($path);
    }

    private function isRealDirectory(string $path): bool
    {
        return is_dir($path) && ! is_link($path);
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
