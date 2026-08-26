<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenKOS\Platform\Plugin\Plugin;
use Throwable;

final class RuntimePluginDiscovery
{
    public function __construct(
        private RuntimePluginStore $store,
        private RuntimePluginArtifactValidator $validator,
        private RuntimePluginGraphValidator $graph,
    ) {}

    /**
     * @param  array<int, string>  $existingClasses
     * @return array<int, class-string<Plugin>>
     */
    public function discover(array $existingClasses = []): array
    {
        if (! config('platform.runtime.enabled', true)) {
            return [];
        }

        try {
            return $this->store->withLock(function (RuntimePluginStore $store) use ($existingClasses): array {
                $state = $store->readState();
                $packages = $store->installedPackages();
                $conflictingIds = $this->assertNoComposerConflicts($packages, $state, $existingClasses);
                $runtime = [];

                foreach ($packages as $id => $path) {
                    $enabled = $state[$id]['enabled'] ?? false;

                    try {
                        $runtime[$id] = [
                            'metadata' => $this->validator->validate($path, $id),
                            'enabled' => $enabled,
                        ];
                    } catch (Throwable $exception) {
                        Log::error('Runtime plugin could not be loaded.', [
                            'plugin' => $id,
                            'path' => $path,
                            'exception' => $exception,
                        ]);
                        $runtime[$id] = [
                            'metadata' => null,
                            'enabled' => $enabled,
                            'status' => 'broken',
                            'error' => 'Runtime plugin artifact validation failed.',
                        ];
                    }
                }

                $report = $this->graph->validate($runtime, $existingClasses);
                $plugins = [];

                foreach ($report['issues'] as $id => $issue) {
                    if (($runtime[$id]['enabled'] ?? false) && ! in_array($id, $conflictingIds, true)) {
                        Log::error('Runtime plugin skipped because its boot graph is invalid.', [
                            'plugin' => $id,
                            'status' => $issue['status'],
                            'error' => $issue['error'],
                        ]);
                    }
                }

                foreach ($report['loadable'] as $id) {
                    $plugins[] = $runtime[$id]['metadata']['entry_class'];
                }

                return $plugins;
            });
        } catch (Throwable $exception) {
            Log::error('Runtime plugin discovery failed.', [
                'path' => $this->store->rootPath(),
                'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, string>  $packages
     * @param  array<string, array{enabled: bool}>  $state
     * @param  array<int, string>  $existingClasses
     * @return array<int, string>
     */
    private function assertNoComposerConflicts(array $packages, array $state, array $existingClasses): array
    {
        $existingIds = [];
        $conflictingIds = [];
        foreach ($existingClasses as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_a($class, Plugin::class, true)) {
                continue;
            }

            try {
                $existingIds[app()->make($class)->manifest()->id] = $class;
            } catch (Throwable) {
                continue;
            }
        }

        foreach ($packages as $id => $path) {
            if (! ($state[$id]['enabled'] ?? false)) {
                continue;
            }

            try {
                $entryClass = $this->readEntryClass($path);
            } catch (Throwable $exception) {
                Log::error('Runtime plugin manifest could not be inspected for conflicts.', [
                    'plugin' => $id,
                    'path' => $path,
                    'exception' => $exception,
                ]);

                continue;
            }

            if (in_array($entryClass, $existingClasses, true) || isset($existingIds[$id])) {
                $conflictingIds[] = $id;
                Log::warning('Runtime plugin skipped because a Composer or explicit plugin takes precedence.', [
                    'plugin' => $id,
                    'path' => $path,
                ]);
            }
        }

        return $conflictingIds;
    }

    private function readEntryClass(string $path): string
    {
        $manifestPath = $path.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException('Runtime plugin manifest is missing.');
        }

        $contents = file_get_contents($manifestPath);
        if ($contents === false) {
            throw new InvalidArgumentException('Runtime plugin manifest cannot be read.');
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Runtime plugin manifest is malformed.', previous: $exception);
        }

        if (! is_array($manifest) || ! is_string($manifest['entry_class'] ?? null)) {
            throw new InvalidArgumentException('Runtime plugin manifest entry class is missing.');
        }

        return $manifest['entry_class'];
    }
}
