<?php

namespace App\Services\Platform;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenKOS\Platform\Plugin\Plugin;
use Throwable;

class PluginManagementService
{
    public function __construct(
        private RuntimePluginStore $store,
        private RuntimePluginArtifactValidator $validator,
        private ComposerPluginDiscovery $composer,
        private RuntimePluginDiscovery $discovery,
        private PluginInstaller $installer,
        private RuntimePluginGraphValidator $graph,
    ) {}

    /**
     * @return array{plugins: array<int, array<string, mixed>>, error: string|null}
     */
    public function catalog(): array
    {
        $legacy = $this->legacyPlugins();
        $runtime = [];
        $error = null;

        try {
            $runtime = $this->runtimePlugins();
        } catch (Throwable $exception) {
            report($exception);
            $error = __('Runtime plugin state could not be read. Check the application logs for details.');
        }

        $this->markConflicts($runtime, $legacy);

        return [
            'plugins' => [...$legacy, ...$runtime],
            'error' => $error,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     entry_class: class-string<Plugin>,
     *     core_version: string,
     *     php: string,
     *     dependencies: array<int, string>
     * }
     */
    public function install(UploadedFile $file): array
    {
        $disk = Storage::disk('local');
        $path = $file->storeAs('plugin-uploads', Str::uuid()->toString().'.zip', 'local');

        if (! is_string($path)) {
            throw new \RuntimeException('Plugin upload could not be stored.');
        }

        try {
            return $this->installer->install($disk->path($path));
        } finally {
            $disk->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(string $id): array
    {
        return $this->installer->enable($id);
    }

    public function disable(string $id, bool $force = false): void
    {
        $this->installer->disable($id, $force);
    }

    public function remove(string $id, bool $force = false): void
    {
        $this->installer->remove($id, $force);
    }

    public function cleanupOrphanedMetadata(?string $recoveryId = null, ?string $cleanupKey = null): void
    {
        $this->installer->cleanupOrphanedMetadata($recoveryId, $cleanupKey);
    }

    public function userMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if ($exception instanceof RuntimePluginDependencyException) {
            return __($exception->getMessage());
        }

        if ($exception instanceof RuntimePluginConflictException || str_contains($message, 'conflict')) {
            return __('This plugin conflicts with an explicit or Composer-installed plugin. Disable or remove the runtime copy.');
        }

        if (str_contains($message, 'could not remove runtime plugin path')) {
            return __('The runtime plugin could not be removed. Check runtime storage permissions and try again.');
        }

        if (str_contains($message, 'could not clean runtime lifecycle path')) {
            return __('Runtime plugin metadata could not be cleaned. Check runtime storage permissions and try again.');
        }

        if (str_contains($message, 'orphaned runtime artifacts')) {
            return __('Clean up orphaned runtime artifacts before installing another plugin.');
        }

        if (
            str_contains($message, 'constraint')
            || str_contains($message, 'dependency')
            || str_contains($message, 'php version')
            || str_contains($message, 'not installed')
            || str_contains($message, 'absent')
            || str_contains($message, 'not bundled')
        ) {
            return __('This plugin is not compatible with the current OpenKOS or PHP installation.');
        }

        return __('The plugin artifact was rejected. Check that it is a valid prepared runtime plugin ZIP.');
    }

    /** @return array<int, array<string, mixed>> */
    private function runtimePlugins(): array
    {
        $recoveryStatus = $this->store->recoveryStatus();

        try {
            $state = $this->store->readState();
        } catch (Throwable $exception) {
            report($exception);

            return $this->runtimePluginsWithCorruptState($recoveryStatus);
        }

        $packages = $this->store->managedPackageEntries();
        $hostClasses = $this->hostPluginClasses();
        $conflictingIds = $this->discovery->conflictingIds(
            $this->store->installedPackages(),
            $state,
            $hostClasses,
        );
        $runtime = [];
        $rows = [];
        $filesystemAnomalies = $this->store->managedFilesystemAnomalies();

        foreach ($packages as $id => $path) {
            $enabled = $state[$id]['enabled'] ?? false;

            if (array_key_exists('package:'.$id, $filesystemAnomalies)) {
                $runtime[$id] = [
                    'metadata' => null,
                    'enabled' => $enabled,
                    'status' => 'broken',
                    'error' => __('Runtime plugin package path is not a real directory.'),
                    'cleanup_key' => 'package:'.$id,
                ];

                continue;
            }

            if (is_link($path)) {
                $runtime[$id] = [
                    'metadata' => null,
                    'enabled' => $enabled,
                    'status' => 'broken',
                    'error' => 'Runtime plugin package path is a symlink and cannot be loaded.',
                    'cleanup_key' => 'package:'.$id,
                ];

                continue;
            }

            if (in_array($id, $conflictingIds, true)) {
                try {
                    $runtime[$id] = [
                        'metadata' => $this->validator->inspectStaticMetadata($path, $id),
                        'enabled' => $enabled,
                        'status' => 'conflict',
                        'error' => 'Runtime plugin conflicts with a Composer or explicit plugin.',
                    ];
                } catch (Throwable) {
                    $runtime[$id] = [
                        'metadata' => null,
                        'enabled' => $enabled,
                        'status' => 'conflict',
                        'error' => 'Runtime plugin conflicts with a Composer or explicit plugin.',
                    ];
                }

                continue;
            }

            if (! $enabled) {
                try {
                    $runtime[$id] = [
                        'metadata' => $this->validator->inspectStaticMetadata($path, $id),
                        'enabled' => false,
                    ];
                } catch (Throwable $exception) {
                    report($exception);
                    $runtime[$id] = [
                        'metadata' => null,
                        'enabled' => false,
                        'status' => $this->validationStatus($exception),
                        'error' => $this->validationError($exception),
                    ];
                }

                continue;
            }

            try {
                $runtime[$id] = [
                    'metadata' => $this->validator->inspectStaticMetadata($path, $id),
                    'enabled' => $enabled,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $runtime[$id] = [
                    'metadata' => null,
                    'enabled' => $enabled,
                    'status' => $this->validationStatus($exception),
                    'error' => $this->validationError($exception),
                ];
            }

            $discoveryFailure = $this->composer->runtimeFailureFor($id);
            if ($discoveryFailure !== null) {
                $runtime[$id]['status'] = $discoveryFailure['status'];
                $runtime[$id]['error'] = $discoveryFailure['error'];
            }
        }

        $health = $this->graph->validate($runtime, $hostClasses);

        foreach ($packages as $id => $path) {
            $entry = $runtime[$id];
            $enabled = $entry['enabled'];

            if (isset($entry['cleanup_key'])) {
                $rows[] = [
                    ...$this->filesystemAnomalyRow($entry['cleanup_key'], $path),
                    'enabled' => $enabled,
                ];

                continue;
            }

            if (is_array($entry['metadata'])) {
                $metadata = $entry['metadata'];
                $issue = $health['issues'][$id] ?? null;

                if ($issue !== null) {
                    $rows[] = [
                        ...$this->runtimeRow($metadata, $enabled),
                        'status' => $issue['status'],
                        'error' => __($issue['error']),
                        'can_enable' => false,
                        'can_disable' => $enabled,
                        'can_remove' => true,
                        'can_force_recovery' => true,
                        'can_cleanup' => false,
                        'cleanup_key' => null,
                    ];

                    continue;
                }

                $lifecycleFailure = $this->lifecycleFailure($metadata['id'], $metadata['entry_class'] ?? null);

                if ($lifecycleFailure !== null) {
                    $rows[] = [
                        ...$this->runtimeRow($metadata, $enabled),
                        'status' => 'load_failed',
                        'error' => __('Runtime plugin failed during :phase and was not loaded. Disable or remove it.', [
                            'phase' => $lifecycleFailure['phase'],
                        ]),
                        'can_enable' => false,
                        'can_disable' => $enabled,
                        'can_remove' => true,
                    ];

                    continue;
                }

                $rows[] = $this->runtimeRow($metadata, $enabled);

                continue;
            }

            $status = $entry['status'];
            $canRemove = ! is_link($path);
            $rows[] = [
                ...$this->manifestPreview($path, $id),
                'source' => 'runtime',
                'status' => $status,
                'enabled' => $enabled,
                'error' => $entry['error'],
                'can_enable' => false,
                'can_disable' => $enabled,
                'can_remove' => $canRemove,
                'can_force_recovery' => $canRemove,
                'can_cleanup' => false,
                'cleanup_key' => $entry['cleanup_key'] ?? null,
            ];
        }

        foreach (array_diff_key($state, $packages) as $id => $entry) {
            $vendor = strstr($id, '/', true);
            $blockedByVendorAnomaly = is_string($vendor)
                && array_key_exists('vendor:'.$vendor, $filesystemAnomalies);

            $rows[] = [
                'id' => $id,
                'managed_id' => $id,
                'declared_id' => null,
                'name' => $id,
                'version' => null,
                'description' => '',
                'entry_class' => null,
                'core_version' => null,
                'php' => null,
                'dependencies' => [],
                'source' => 'runtime',
                'status' => 'missing_package',
                'enabled' => $entry['enabled'],
                'error' => $blockedByVendorAnomaly
                    ? __('An unsafe vendor path blocks this stale plugin state. Clean up the vendor filesystem anomaly first.')
                    : __('Plugin state exists without an installed package. Remove the stale state entry.'),
                'can_enable' => false,
                'can_disable' => false,
                'can_remove' => ! $blockedByVendorAnomaly,
                'can_force_recovery' => ! $blockedByVendorAnomaly,
                'can_cleanup' => false,
                'cleanup_key' => null,
            ];
        }

        foreach ($filesystemAnomalies as $key => $path) {
            if (str_starts_with($key, 'package:') && array_key_exists(substr($key, strlen('package:')), $packages)) {
                continue;
            }

            $rows[] = $this->filesystemAnomalyRow($key, $path);
        }

        $recoveryRecords = $this->store->recoveryRecords();

        foreach ($recoveryRecords as $record) {
            $recoveryId = $record['id'];

            if ($recoveryId === null) {
                if (! $this->hasRecoveryFilesystemAnomaly($filesystemAnomalies)) {
                    $rows[] = $this->orphanedMetadataRow(
                        $record['status'],
                        cleanupKey: $record['cleanup_key'],
                    );
                }

                continue;
            }

            $matched = false;

            foreach ($rows as &$row) {
                if (
                    $row['source'] !== 'runtime'
                    || ($row['cleanup_key'] ?? null) !== null
                    || $row['managed_id'] !== $recoveryId
                ) {
                    continue;
                }

                $matched = true;

                if ($record['status'] === RuntimePluginStore::RECOVERY_PENDING) {
                    $row['status'] = RuntimePluginStore::RECOVERY_PENDING;
                    $row['error'] = __('Runtime plugin recovery is pending and will complete before the next lifecycle change.');
                    $row['can_enable'] = false;
                    $row['can_disable'] = false;
                    $row['can_remove'] = true;
                    $row['can_force_recovery'] = false;
                } elseif ($record['status'] === RuntimePluginStore::RECOVERY_UNRECOVERABLE) {
                    $row['status'] = RuntimePluginStore::RECOVERY_UNRECOVERABLE;
                    $row['error'] = __('Runtime plugin recovery cannot be completed. Force remove this package or clean up orphaned metadata.');
                    $row['can_enable'] = false;
                    $row['can_disable'] = false;
                    $row['can_remove'] = false;
                    $row['can_force_recovery'] = true;
                }
            }
            unset($row);

            if (! $matched) {
                $rows[] = $this->recoveryRow($record['status'], $recoveryId);
            }
        }

        if ($recoveryRecords === [] && $recoveryStatus === RuntimePluginStore::RECOVERY_ORPHANED_ARTIFACT) {
            $rows[] = $this->orphanedMetadataRow($recoveryStatus, cleanupKey: 'orphaned-artifacts');
        } elseif ($recoveryRecords === [] && $rows === [] && $recoveryStatus !== RuntimePluginStore::RECOVERY_HEALTHY) {
            $rows[] = $this->orphanedMetadataRow($recoveryStatus);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function runtimePluginsWithCorruptState(string $recoveryStatus): array
    {
        $rows = [];

        foreach ($this->store->managedPackageEntries() as $id => $path) {
            $canRemove = ! is_link($path);

            if (is_link($path)) {
                $rows[] = [
                    ...$this->filesystemAnomalyRow('package:'.$id, $path),
                    'enabled' => false,
                ];

                continue;
            }

            $rows[] = [
                ...$this->manifestPreview($path, $id),
                'source' => 'runtime',
                'status' => 'broken',
                'enabled' => false,
                'error' => __('Runtime plugin state is corrupted. Force remove this package to recover it.'),
                'can_enable' => false,
                'can_disable' => false,
                'can_remove' => false,
                'can_force_recovery' => $canRemove,
                'can_cleanup' => false,
                'cleanup_key' => null,
            ];
        }

        $filesystemAnomalies = $this->store->managedFilesystemAnomalies();

        foreach ($filesystemAnomalies as $key => $path) {
            if (str_starts_with($key, 'package:')) {
                continue;
            }

            $rows[] = $this->filesystemAnomalyRow($key, $path);
        }

        if ($rows === [] && $recoveryStatus !== RuntimePluginStore::RECOVERY_UNRECOVERABLE) {
            $rows[] = $this->orphanedMetadataRow('orphaned_state', $recoveryStatus);
        }

        $recoveryRecords = $this->store->recoveryRecords();

        foreach ($recoveryRecords as $record) {
            $recoveryId = $record['id'];

            if ($recoveryId === null) {
                if (! $this->hasRecoveryFilesystemAnomaly($filesystemAnomalies)) {
                    $rows[] = $this->orphanedMetadataRow(
                        $record['status'],
                        cleanupKey: $record['cleanup_key'],
                    );
                }

                continue;
            }

            $matched = false;

            foreach ($rows as &$row) {
                if (($row['cleanup_key'] ?? null) !== null || $row['managed_id'] !== $recoveryId) {
                    continue;
                }

                $matched = true;
                $row['status'] = $record['status'];
                $row['error'] = __('Runtime plugin state and recovery metadata cannot be completed. Clean up orphaned metadata.');
                $row['can_enable'] = false;
                $row['can_disable'] = false;
                $row['can_remove'] = false;
                $row['can_force_recovery'] = true;
                $row['can_cleanup'] = false;
            }
            unset($row);

            if (! $matched) {
                $rows[] = $this->recoveryRow($record['status'], $recoveryId);
            }
        }

        if ($recoveryRecords === [] && $recoveryStatus === RuntimePluginStore::RECOVERY_ORPHANED_ARTIFACT) {
            $rows[] = $this->orphanedMetadataRow($recoveryStatus, cleanupKey: 'orphaned-artifacts');
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyPlugins(): array
    {
        $configured = array_values(array_filter(config('platform.plugins', []), 'is_string'));
        $discovered = [];

        try {
            $discovered = $this->composer->discoverComposerOnly();
        } catch (Throwable $exception) {
            report($exception);
        }

        $rows = [];

        foreach (array_values(array_unique([...$configured, ...$discovered])) as $class) {
            try {
                if (! class_exists($class) || ! is_a($class, Plugin::class, true)) {
                    throw new \InvalidArgumentException('Plugin class is invalid.');
                }

                $manifest = app()->make($class)->manifest();
                $rows[] = [
                    'id' => $manifest->id,
                    'managed_id' => $manifest->id,
                    'declared_id' => $manifest->id,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'entry_class' => $class,
                    'core_version' => $manifest->coreVersion,
                    'php' => null,
                    'dependencies' => $manifest->dependencies,
                    'source' => in_array($class, $configured, true) ? 'explicit' : 'composer',
                    'status' => 'legacy',
                    'enabled' => true,
                    'error' => null,
                    'can_enable' => false,
                    'can_disable' => false,
                    'can_remove' => false,
                    'can_force_recovery' => false,
                    'can_cleanup' => false,
                    'cleanup_key' => null,
                ];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $metadata */
    private function runtimeRow(array $metadata, bool $enabled): array
    {
        return [
            ...$metadata,
            'managed_id' => $metadata['id'],
            'declared_id' => $metadata['id'],
            'source' => 'runtime',
            'status' => $enabled ? 'enabled' : 'disabled',
            'enabled' => $enabled,
            'error' => null,
            'can_enable' => ! $enabled,
            'can_disable' => $enabled,
            'can_remove' => true,
            'can_force_recovery' => false,
            'can_cleanup' => false,
            'cleanup_key' => null,
        ];
    }

    /** @return array{phase: string}|null */
    private function lifecycleFailure(string $id, ?string $entryClass): ?array
    {
        $registryClass = 'OpenKOS\\Platform\\Plugin\\PluginLifecycleFailureRegistry';

        if (! class_exists($registryClass) || ! app()->bound($registryClass)) {
            return null;
        }

        $failure = app($registryClass)->forPlugin($id, $entryClass);

        return is_array($failure) && is_string($failure['phase'] ?? null)
            ? ['phase' => $failure['phase']]
            : null;
    }

    /** @return array<string, mixed> */
    private function orphanedMetadataRow(string $status, ?string $recoveryStatus = null, ?string $cleanupKey = null): array
    {
        $unknownRecovery = $cleanupKey === 'orphaned-recovery';
        $error = match ($status) {
            'orphaned_state' => __('Runtime lifecycle metadata is corrupted without an installed package. Clean it up before installing another plugin.'),
            RuntimePluginStore::RECOVERY_ORPHANED_ARTIFACT => __('Stale runtime recovery artifacts were found. Clean them up before installing another plugin.'),
            default => __('Runtime plugin recovery cannot be completed without an installed package. Clean up the orphaned lifecycle metadata.'),
        };

        if ($recoveryStatus === RuntimePluginStore::RECOVERY_UNRECOVERABLE) {
            $error = __('Runtime lifecycle metadata is corrupted and cannot be recovered. Clean it up before installing another plugin.');
        }

        return [
            'id' => 'Runtime lifecycle metadata',
            'managed_id' => null,
            'declared_id' => null,
            'name' => __('Runtime lifecycle metadata'),
            'version' => null,
            'description' => $unknownRecovery
                ? __('Runtime recovery metadata exists, but its package identity cannot be trusted.')
                : __('No runtime package is installed, but lifecycle metadata still requires recovery.'),
            'entry_class' => null,
            'core_version' => null,
            'php' => null,
            'dependencies' => [],
            'source' => 'runtime',
            'status' => $status,
            'enabled' => false,
            'error' => $error,
            'can_enable' => false,
            'can_disable' => false,
            'can_remove' => false,
            'can_force_recovery' => false,
            'can_cleanup' => $cleanupKey !== null || $status !== RuntimePluginStore::RECOVERY_PENDING,
            'cleanup_key' => $cleanupKey,
        ];
    }

    /** @return array<string, mixed> */
    private function recoveryRow(string $status, string $id): array
    {
        return [
            'id' => $id,
            'managed_id' => $id,
            'declared_id' => null,
            'name' => __('Runtime plugin recovery'),
            'version' => null,
            'description' => __('Recovery metadata exists for a package that is not installed.'),
            'entry_class' => null,
            'core_version' => null,
            'php' => null,
            'dependencies' => [],
            'source' => 'runtime',
            'status' => $status,
            'enabled' => false,
            'error' => $status === RuntimePluginStore::RECOVERY_PENDING
                ? __('Runtime plugin recovery is pending and will complete before the next lifecycle change.')
                : __('Runtime plugin recovery cannot be completed. Clean up this recovery record.'),
            'can_enable' => false,
            'can_disable' => false,
            'can_remove' => false,
            'can_force_recovery' => false,
            'can_cleanup' => true,
            'cleanup_key' => 'recovery:'.$id,
        ];
    }

    /** @return array<string, mixed> */
    private function filesystemAnomalyRow(string $key, ?string $path = null): array
    {
        $managedId = str_starts_with($key, 'package:') ? substr($key, strlen('package:')) : null;
        $label = $managedId ?? substr($key, strpos($key, ':') + 1);
        $canCleanup = $path === null || $this->canCleanupFilesystemPath($path);
        $isSymlink = $path !== null && is_link($path);

        return [
            'id' => $managedId ?? $key,
            'managed_id' => $managedId,
            'declared_id' => null,
            'name' => __('Unsafe runtime filesystem path'),
            'version' => null,
            'description' => $isSymlink
                ? __('A managed runtime path is a symlink and was not followed.')
                : __('A managed runtime path has an unexpected filesystem type.'),
            'entry_class' => null,
            'core_version' => null,
            'php' => null,
            'dependencies' => [],
            'source' => 'runtime',
            'status' => 'broken',
            'enabled' => false,
            'error' => $canCleanup
                ? ($isSymlink
                    ? __('Runtime path :path is a symlink. Remove the symlink before installing or loading plugins.', ['path' => $label])
                    : __('Runtime path :path is an unexpected filesystem node and is not used.', ['path' => $label]))
                : __('Runtime path :path cannot be safely inspected or removed. Fix its permissions before continuing.', ['path' => $label]),
            'can_enable' => false,
            'can_disable' => false,
            'can_remove' => false,
            'can_force_recovery' => false,
            'can_cleanup' => $canCleanup,
            'cleanup_key' => $key,
        ];
    }

    private function canCleanupFilesystemPath(string $path): bool
    {
        if (@lstat($path) === false || @scandir(dirname($path)) === false) {
            return false;
        }

        if (is_link($path)) {
            return true;
        }

        return ! is_dir($path) || @scandir($path) !== false;
    }

    /** @param array<string, string> $anomalies */
    private function hasRecoveryFilesystemAnomaly(array $anomalies): bool
    {
        foreach (array_keys($anomalies) as $key) {
            if ($key === 'internal:.recovery' || str_starts_with($key, 'internal:.recovery/')) {
                return true;
            }
        }

        return array_key_exists('internal:recovery.json', $anomalies);
    }

    /** @return array<string, mixed> */
    private function manifestPreview(string $path, string $id): array
    {
        $preview = [
            'id' => $id,
            'managed_id' => $id,
            'declared_id' => null,
            'name' => $id,
            'version' => null,
            'description' => '',
            'entry_class' => null,
            'core_version' => null,
            'php' => null,
            'dependencies' => [],
        ];

        if (is_link($path) || ! is_dir($path)) {
            return $preview;
        }

        $manifestPath = $path.'/manifest.json';

        if (! is_file($manifestPath) || is_link($manifestPath)) {
            return $preview;
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            return $preview;
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $preview;
        }

        if (! is_array($manifest)) {
            return $preview;
        }

        foreach (['id', 'name', 'version', 'description', 'entry_class', 'core_version', 'php'] as $key) {
            if (is_string($manifest[$key] ?? null) && $manifest[$key] !== '') {
                if ($key === 'id') {
                    $preview['declared_id'] = $manifest[$key];
                } else {
                    $preview[$key] = $manifest[$key];
                }
            }
        }

        if (is_array($manifest['dependencies'] ?? null)) {
            $preview['dependencies'] = array_values(array_filter($manifest['dependencies'], 'is_string'));
        }

        return $preview;
    }

    private function validationStatus(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'constraint')
            || str_contains($message, 'dependency')
            || str_contains($message, 'php version')
            || str_contains($message, 'not installed')
            || str_contains($message, 'absent')
            || str_contains($message, 'not bundled')
            ? 'incompatible'
            : 'broken';
    }

    private function validationError(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'does not match installed package')) {
            return __('Manifest plugin ID does not match installed package identity.');
        }

        return $this->validationStatus($exception) === 'incompatible'
            ? __('This plugin is not compatible with the current OpenKOS or PHP installation.')
            : __('This plugin is incomplete or failed validation. Disable or remove it, then install a valid artifact.');
    }

    /** @return array<int, string> */
    private function hostPluginClasses(): array
    {
        $classes = array_values(array_filter(config('platform.plugins', []), 'is_string'));

        try {
            $classes = [...$classes, ...$this->composer->discoverComposerOnly()];
        } catch (Throwable $exception) {
            report($exception);
        }

        return array_values(array_unique(array_filter($classes, 'is_string')));
    }

    /** @param array<int, array<string, mixed>> $runtime @param array<int, array<string, mixed>> $legacy */
    private function markConflicts(array &$runtime, array &$legacy): void
    {
        $legacyIds = array_count_values(array_column($legacy, 'id'));
        $legacyClasses = array_column($legacy, 'entry_class');
        $legacyClassNames = array_map(
            fn (mixed $class): string => is_string($class) ? $this->canonicalClassName($class) : '',
            $legacyClasses,
        );

        foreach ($legacy as &$plugin) {
            if (($legacyIds[$plugin['id']] ?? 0) > 1) {
                $this->markConflict($plugin, __('Multiple Composer or explicit plugins declare the same identity.'));
            }
        }
        unset($plugin);

        foreach ($runtime as &$plugin) {
            if (in_array($plugin['status'], [
                'broken',
                'incompatible',
                'missing_package',
                'orphaned_state',
                RuntimePluginStore::RECOVERY_PENDING,
                RuntimePluginStore::RECOVERY_UNRECOVERABLE,
            ], true)) {
                continue;
            }

            if (
                in_array($plugin['id'], array_column($legacy, 'id'), true)
                || in_array($this->canonicalClassName((string) $plugin['entry_class']), $legacyClassNames, true)
            ) {
                $this->markConflict($plugin, __('A Composer or explicit plugin has the same identity; this runtime copy is not loaded.'));

                foreach ($legacy as &$legacyPlugin) {
                    if (
                        $legacyPlugin['id'] === $plugin['id']
                        || $this->canonicalClassName((string) $legacyPlugin['entry_class']) === $this->canonicalClassName((string) $plugin['entry_class'])
                    ) {
                        $this->markConflict($legacyPlugin, __('A runtime plugin has the same identity; the Composer or explicit copy takes precedence.'));
                    }
                }
                unset($legacyPlugin);
            }
        }
        unset($plugin);
    }

    /** @param array<string, mixed> $plugin */
    private function markConflict(array &$plugin, string $message): void
    {
        $plugin['status'] = 'conflict';
        $plugin['error'] = $message;
        $plugin['can_enable'] = false;
        $plugin['can_disable'] = $plugin['source'] === 'runtime' && $plugin['enabled'];
        $plugin['can_remove'] = $plugin['source'] === 'runtime';
        $plugin['can_force_recovery'] = $plugin['source'] === 'runtime';
    }

    private function canonicalClassName(string $class): string
    {
        return strtolower(ltrim(trim($class), '\\'));
    }
}
