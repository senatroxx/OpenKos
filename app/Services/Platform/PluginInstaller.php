<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use OpenKOS\Platform\Plugin\Plugin;
use RuntimeException;
use Throwable;
use ZipArchive;

final class PluginInstaller
{
    public function __construct(
        private RuntimePluginStore $store,
        private RuntimePluginArtifactValidator $validator,
        private RuntimePluginGraphValidator $graph,
        private ComposerPluginDiscovery $composer,
        private RuntimePluginDiscovery $discovery,
    ) {}

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
    public function install(string $zipPath): array
    {
        if (! config('platform.runtime.enabled', true)) {
            throw new RuntimeException('Runtime plugin installation is disabled.');
        }

        if (! is_file($zipPath) || is_link($zipPath)) {
            throw new InvalidArgumentException('Plugin ZIP file does not exist or is unsafe.');
        }

        $inspectionPath = null;

        try {
            $archive = new ZipArchive;
            $opened = $archive->open($zipPath);

            if ($opened !== true) {
                throw new InvalidArgumentException('Plugin ZIP file cannot be opened.');
            }

            try {
                $this->validateArchiveEntries($archive);
                $inspectionPath = $this->createInspectionPath();

                if (! $archive->extractTo($inspectionPath)) {
                    throw new RuntimeException('Plugin ZIP could not be extracted for inspection.');
                }
            } finally {
                $archive->close();
            }

            $staticMetadata = $this->validator->inspectStaticMetadata($inspectionPath);
            $pluginId = $staticMetadata['id'];

            return $this->store->withLock(function (RuntimePluginStore $store) use (&$inspectionPath, $pluginId): array {
                $stagingPath = null;

                try {
                    $stagingPath = $store->createStagingPath('incoming');

                    if (! rename($inspectionPath, $stagingPath)) {
                        throw new RuntimeException('Plugin ZIP could not be moved into staging storage.');
                    }

                    $inspectionPath = null;
                    $state = $store->readState();
                    $staticMetadata = $this->validator->inspectStaticMetadata($stagingPath, $pluginId);
                    $enabled = ! isset($state[$pluginId]) || $state[$pluginId]['enabled'];
                    $candidatePackages = $store->installedPackages();
                    $candidatePackages[$pluginId] = $stagingPath;

                    if (in_array($pluginId, $this->discovery->conflictingIds(
                        $candidatePackages,
                        [...$state, $pluginId => ['enabled' => $enabled]],
                        $this->hostPluginClasses(),
                        true,
                    ), true)) {
                        throw new RuntimePluginConflictException('Runtime plugin conflicts with an existing plugin.');
                    }

                    $metadata = $this->validator->validateInFreshProcess($stagingPath, $staticMetadata['id']);
                    $this->graph->validateCandidate($metadata, $enabled, $store, $this->hostPluginClasses());
                    $store->promote($metadata['id'], $stagingPath, $enabled);
                    $stagingPath = null;

                    return $metadata;
                } catch (Throwable $exception) {
                    if (is_string($stagingPath)) {
                        $store->discardStaging($stagingPath);
                    }

                    throw $exception;
                }
            }, true, $pluginId);
        } finally {
            if (is_string($inspectionPath)) {
                $this->discardInspectionPath($inspectionPath);
            }
        }
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
    public function enable(string $id): array
    {
        return $this->store->withLock(function (RuntimePluginStore $store) use ($id): array {
            $path = $store->installedPackagePath($id);
            $state = $store->readState();

            if (in_array($id, $this->discovery->conflictingIds(
                $store->installedPackages(),
                [...$state, $id => ['enabled' => true]],
                $this->hostPluginClasses(),
            ), true)) {
                throw new RuntimePluginConflictException('Runtime plugin conflicts with an existing plugin.');
            }

            $metadata = $this->validator->validateInFreshProcess($path, $id);
            $this->graph->validateCandidate($metadata, true, $store, $this->hostPluginClasses());
            $store->setEnabled($id, true);

            return $metadata;
        }, true, $id);
    }

    public function disable(string $id, bool $force = false): void
    {
        $this->store->withLock(function (RuntimePluginStore $store) use ($id, $force): void {
            if ($force) {
                try {
                    $store->readState();
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        'Runtime plugin state is corrupted. Remove the package to recover it.',
                        previous: $exception,
                    );
                }
            }

            $this->assertNoEnabledDependants($id, $store, 'disable', $force);
            $store->setEnabled($id, false);
        }, true, $id);
    }

    public function remove(string $id, bool $force = false): void
    {
        $this->store->withLock(function (RuntimePluginStore $store) use ($id, $force): void {
            $recoveryStatus = $store->recoveryStatus();

            if ($force && $recoveryStatus === RuntimePluginStore::RECOVERY_PENDING && $store->hasRecoveryFor($id)) {
                try {
                    $store->recoverPendingOperation($id);
                    $recoveryStatus = $store->recoveryStatus();
                } catch (Throwable $exception) {
                    if ($store->recoveryStatus() !== RuntimePluginStore::RECOVERY_UNRECOVERABLE) {
                        try {
                            $store->readState();
                        } catch (Throwable) {
                            $store->forceRemove($id, true);

                            return;
                        }

                        throw $exception;
                    }

                    $recoveryStatus = RuntimePluginStore::RECOVERY_UNRECOVERABLE;
                }
            }

            if ($force) {
                try {
                    $store->readState();
                } catch (Throwable) {
                    if ($recoveryStatus === RuntimePluginStore::RECOVERY_PENDING) {
                        $store->forceRemove($id, true);

                        return;
                    }

                    $store->forceRemove($id);

                    return;
                }
            }

            $this->assertNoEnabledDependants($id, $store, 'remove', $force);

            if ($force && $recoveryStatus === RuntimePluginStore::RECOVERY_UNRECOVERABLE && $store->hasRecoveryFor($id)) {
                $store->forceRemove($id);

                return;
            }

            $store->remove($id);
        }, ! $force, $id);
    }

    public function cleanupOrphanedMetadata(?string $recoveryId = null, ?string $cleanupKey = null): void
    {
        if ($cleanupKey === 'internal:.lock') {
            $this->store->forceCleanupFilesystemAnomaly($cleanupKey);

            return;
        }

        $this->store->withLock(function (RuntimePluginStore $store) use ($recoveryId, $cleanupKey): void {
            if ($cleanupKey === 'orphaned-artifacts') {
                $store->forceCleanupOrphanedArtifacts();

                return;
            }

            if ($cleanupKey === 'orphaned-recovery') {
                $store->forceCleanupUnknownRecovery();

                return;
            }

            if (str_starts_with($cleanupKey ?? '', 'recovery:')) {
                $recoveryId = substr($cleanupKey, strlen('recovery:'));

                if ($recoveryId === '') {
                    throw new RuntimeException('Runtime plugin recovery identity is missing.');
                }

                $store->forceCleanupRecovery($recoveryId);

                return;
            }

            if ($cleanupKey !== null) {
                $store->forceCleanupFilesystemAnomaly($cleanupKey);

                return;
            }

            if ($recoveryId !== null) {
                $store->forceCleanupRecovery($recoveryId);

                return;
            }

            $store->forceCleanupOrphanedMetadata();
        }, false);
    }

    private function validateArchiveEntries(ZipArchive $archive): void
    {
        $entryCount = 0;
        $size = 0;
        $maxFiles = (int) config('platform.runtime.max_files', 5000);
        $maxSize = (int) config('platform.runtime.max_uncompressed_bytes', 268_435_456);
        $seen = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);

            if (! is_array($stat) || ! is_string($stat['name'] ?? null)) {
                throw new InvalidArgumentException('Plugin ZIP contains an unreadable entry.');
            }

            $name = $stat['name'];
            $normalizedName = str_replace('\\', '/', $name);
            $normalizedPath = rtrim($normalizedName, '/');
            $segments = explode('/', $normalizedName);
            $isDirectory = str_ends_with($normalizedName, '/');

            if (
                $normalizedName === '' ||
                str_contains($normalizedName, "\0") ||
                str_starts_with($normalizedName, '/') ||
                preg_match('/^[A-Za-z]:\//', $normalizedName) === 1 ||
                in_array('..', $segments, true) ||
                in_array('.', $segments, true) ||
                in_array('', array_slice($segments, 0, -1), true)
            ) {
                throw new InvalidArgumentException("Plugin ZIP contains an unsafe path [{$name}].");
            }

            if ($normalizedPath === '' || isset($seen[$normalizedPath])) {
                throw new InvalidArgumentException("Plugin ZIP contains a duplicate path [{$name}].");
            }

            $seen[$normalizedPath] = true;
            $entryCount++;

            $topLevel = $segments[0] ?? '';
            if (! in_array($topLevel, [
                'manifest.json',
                'composer.json',
                'composer.lock',
                'src',
                'vendor',
                'config',
                'routes',
                'database',
                'resources',
            ], true)) {
                throw new InvalidArgumentException("Plugin ZIP contains an unexpected path [{$name}].");
            }

            $externalAttributes = 0;
            $operatingSystem = 0;
            if ($archive->getExternalAttributesIndex($index, $operatingSystem, $externalAttributes)) {
                $mode = ($externalAttributes >> 16) & 0xF000;
                $permissions = ($externalAttributes >> 16) & 0111;

                if ($mode === 0xA000) {
                    throw new InvalidArgumentException("Plugin ZIP contains a symlink [{$name}].");
                }

                if (! $isDirectory && $permissions !== 0) {
                    throw new InvalidArgumentException("Plugin ZIP contains an executable entry [{$name}].");
                }
            }

            if (! $isDirectory) {
                $size += (int) ($stat['size'] ?? 0);
            }

            if ($entryCount > $maxFiles || $size > $maxSize) {
                throw new InvalidArgumentException('Plugin ZIP exceeds the configured archive limits.');
            }

            if (preg_match('/(^|\/)(install|post-install|pre-install)\.(php|sh|bash|bat|cmd|exe)$/i', $normalizedName)) {
                throw new InvalidArgumentException("Plugin ZIP contains an install script [{$name}].");
            }
        }
    }

    private function createInspectionPath(): string
    {
        $path = dirname($this->store->rootPath()).'/.openkos-plugin-'.bin2hex(random_bytes(8));

        if (! mkdir($path, 0750) || ! is_dir($path) || is_link($path)) {
            throw new RuntimeException('Could not create temporary plugin inspection storage.');
        }

        return $path;
    }

    private function discardInspectionPath(string $path): void
    {
        if (is_link($path) || (! is_dir($path) && file_exists($path))) {
            throw new RuntimeException('Temporary plugin inspection storage is unsafe.');
        }

        if (is_dir($path) && (! File::deleteDirectory($path) || is_dir($path))) {
            throw new RuntimeException('Could not clean temporary plugin inspection storage.');
        }
    }

    /** @return array<int, string> */
    private function hostPluginClasses(): array
    {
        return [
            ...array_values(array_filter(config('platform.plugins', []), 'is_string')),
            ...$this->composer->discoverComposerOnly(),
        ];
    }

    private function assertNoEnabledDependants(string $id, RuntimePluginStore $store, string $action, bool $force = false): void
    {
        $dependants = $this->graph->enabledDependants($id, $store, $this->hostPluginClasses());

        if ($dependants === []) {
            return;
        }

        if ($force && $this->graph->canForceRecover($id, $store, $this->hostPluginClasses())) {
            return;
        }

        throw new RuntimePluginDependencyException(
            "Cannot {$action} {$id} because ".implode(', ', $dependants).' depends on it.',
        );
    }
}
