<?php

namespace App\Services\Platform;

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

        return $this->store->withLock(function (RuntimePluginStore $store) use ($zipPath): array {
            $archive = new ZipArchive;
            $opened = $archive->open($zipPath);

            if ($opened !== true) {
                throw new InvalidArgumentException('Plugin ZIP file cannot be opened.');
            }

            $stagingPath = null;

            try {
                $this->validateArchiveEntries($archive);
                $stagingPath = $store->createStagingPath('incoming');

                if (! $archive->extractTo($stagingPath)) {
                    throw new RuntimeException('Plugin ZIP could not be extracted into staging storage.');
                }

                $metadata = $this->validator->validateInFreshProcess($stagingPath);
                $state = $store->readState();
                $enabled = ! isset($state[$metadata['id']]) || $state[$metadata['id']]['enabled'];
                $this->graph->validateCandidate($metadata, $enabled, $store, $this->hostPluginClasses());
                $store->promote($metadata['id'], $stagingPath, $enabled);
                $stagingPath = null;

                return $metadata;
            } catch (Throwable $exception) {
                if (is_string($stagingPath)) {
                    $store->discardStaging($stagingPath);
                }

                throw $exception;
            } finally {
                $archive->close();
            }
        });
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
            $path = $store->packagePath($id);
            $metadata = $this->validator->validateInFreshProcess($path, $id);
            $this->graph->validateCandidate($metadata, true, $store, $this->hostPluginClasses());
            $store->setEnabled($id, true);

            return $metadata;
        });
    }

    public function disable(string $id): void
    {
        $this->store->withLock(function (RuntimePluginStore $store) use ($id): void {
            $this->assertNoEnabledDependants($id, $store, 'disable');
            $store->setEnabled($id, false);
        });
    }

    public function remove(string $id): void
    {
        $this->store->withLock(function (RuntimePluginStore $store) use ($id): void {
            $this->assertNoEnabledDependants($id, $store, 'remove');
            $store->remove($id);
        });
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

    /** @return array<int, string> */
    private function hostPluginClasses(): array
    {
        return [
            ...array_values(array_filter(config('platform.plugins', []), 'is_string')),
            ...$this->composer->discoverComposerOnly(),
        ];
    }

    private function assertNoEnabledDependants(string $id, RuntimePluginStore $store, string $action): void
    {
        $dependants = $this->graph->enabledDependants($id, $store, $this->hostPluginClasses());

        if ($dependants === []) {
            return;
        }

        throw new RuntimePluginDependencyException(
            "Cannot {$action} {$id} because ".implode(', ', $dependants).' depends on it.',
        );
    }
}
