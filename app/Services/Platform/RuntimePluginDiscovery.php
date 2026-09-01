<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenKOS\Platform\Plugin\Plugin;
use Throwable;

final class RuntimePluginDiscovery
{
    /** @var array<string, array{status: string, error: string}> */
    private array $failures = [];

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
        $this->failures = [];

        if (! config('platform.runtime.enabled', true)) {
            return [];
        }

        try {
            return $this->store->withLock(function (RuntimePluginStore $store) use ($existingClasses): array {
                $state = $store->readState();
                $packages = $store->installedPackages();
                $conflictingIds = $this->conflictingIds($packages, $state, $existingClasses);
                $recoveryStatus = $store->recoveryStatus();
                $recoveryStatuses = [];

                foreach ($store->recoveryRecords() as $record) {
                    if ($record['id'] !== null) {
                        $recoveryStatuses[$record['id']] = $record['status'];
                    }
                }

                $runtime = [];

                if ($recoveryStatuses === [] && $recoveryStatus !== RuntimePluginStore::RECOVERY_HEALTHY) {
                    Log::error('Runtime plugin discovery found recovery state without a trusted package identity.', [
                        'path' => $store->rootPath(),
                        'status' => $recoveryStatus,
                    ]);
                }

                foreach ($packages as $id => $path) {
                    $enabled = $state[$id]['enabled'] ?? false;

                    if (! $enabled) {
                        $runtime[$id] = [
                            'metadata' => null,
                            'enabled' => false,
                        ];

                        continue;
                    }

                    if (
                        isset($recoveryStatuses[$id])
                        && in_array($recoveryStatuses[$id], [
                            RuntimePluginStore::RECOVERY_PENDING,
                            RuntimePluginStore::RECOVERY_UNRECOVERABLE,
                        ], true)
                    ) {
                        $runtime[$id] = [
                            'metadata' => null,
                            'enabled' => $enabled,
                            'status' => $recoveryStatuses[$id],
                            'error' => 'Runtime plugin recovery must complete before the package can be loaded.',
                        ];

                        continue;
                    }

                    if (in_array($id, $conflictingIds, true)) {
                        $runtime[$id] = [
                            'metadata' => null,
                            'enabled' => true,
                            'status' => 'conflict',
                            'error' => 'Runtime plugin conflicts with a Composer or explicit plugin.',
                        ];

                        continue;
                    }

                    try {
                        $runtime[$id] = [
                            'metadata' => $this->validator->inspectStaticMetadata($path, $id),
                            'enabled' => $enabled,
                        ];
                    } catch (Throwable $exception) {
                        Log::error('Runtime plugin could not be inspected.', [
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
                $validated = [];
                $pending = $report['loadable'];

                while ($pending !== []) {
                    $progress = false;

                    foreach ($pending as $key => $id) {
                        $metadata = $runtime[$id]['metadata'];
                        $dependenciesReady = true;

                        foreach ($metadata['dependencies'] ?? [] as $dependency) {
                            if (isset($runtime[$dependency]) && ! isset($validated[$dependency])) {
                                $dependenciesReady = false;
                                break;
                            }
                        }

                        if (! $dependenciesReady) {
                            continue;
                        }

                        unset($pending[$key]);
                        $progress = true;
                        $path = $packages[$id];

                        try {
                            $metadata = $this->validator->validateInFreshProcess($path, $id);
                            require_once $path.'/vendor/autoload.php';

                            if (! class_exists($metadata['entry_class'])) {
                                throw new InvalidArgumentException(
                                    "Runtime plugin entry class [{$metadata['entry_class']}] does not exist.",
                                );
                            }

                            $runtime[$id]['metadata'] = $metadata;
                            $validated[$id] = $metadata['entry_class'];
                            $report = $this->graph->validate($runtime, $existingClasses);
                        } catch (Throwable $exception) {
                            Log::error('Runtime plugin failed fresh-process validation.', [
                                'plugin' => $id,
                                'path' => $path,
                                'exception' => $exception,
                            ]);
                            $runtime[$id]['status'] = 'broken';
                            $runtime[$id]['error'] = 'Runtime plugin failed fresh-process validation.';
                            $this->failures[$id] = [
                                'status' => 'broken',
                                'error' => $runtime[$id]['error'],
                            ];
                            $report = $this->graph->validate($runtime, $existingClasses);
                            $pending = array_values(array_diff($report['loadable'], array_keys($validated)));
                        }
                    }

                    if (! $progress) {
                        break;
                    }
                }

                foreach ($report['issues'] as $id => $issue) {
                    if (($runtime[$id]['enabled'] ?? false) && ! in_array($id, $conflictingIds, true)) {
                        Log::error('Runtime plugin skipped because its boot graph is invalid.', [
                            'plugin' => $id,
                            'status' => $issue['status'],
                            'error' => $issue['error'],
                        ]);
                    }
                }

                return array_values(array_filter(
                    array_map(fn (string $id): ?string => $validated[$id] ?? null, $report['loadable']),
                    'is_string',
                ));
            }, false);
        } catch (Throwable $exception) {
            Log::error('Runtime plugin discovery failed.', [
                'path' => $this->store->rootPath(),
                'exception' => $exception,
            ]);

            return [];
        }
    }

    /** @return array{status: string, error: string}|null */
    public function failureFor(string $id): ?array
    {
        return $this->failures[$id] ?? null;
    }

    /**
     * @param  array<string, string>  $packages
     * @param  array<string, array{enabled: bool}>  $state
     * @param  array<int, string>  $existingClasses
     * @return array<int, string>
     */
    public function conflictingIds(array $packages, array $state, array $existingClasses, bool $includeDisabled = false): array
    {
        $existingIds = [];
        $conflictingIds = [];
        $runtimeEntryClasses = [];
        $existingClassNames = array_fill_keys(array_map(
            fn (string $class): string => $this->canonicalClassName($class),
            array_values(array_filter($existingClasses, 'is_string')),
        ), true);
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
            if (! $includeDisabled && ! ($state[$id]['enabled'] ?? false)) {
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

            $canonicalEntryClass = $this->canonicalClassName($entryClass);
            $runtimeEntryClasses[$canonicalEntryClass][] = $id;

            if (isset($existingClassNames[$canonicalEntryClass]) || isset($existingIds[$id])) {
                $conflictingIds[] = $id;
                Log::warning('Runtime plugin skipped because a Composer or explicit plugin takes precedence.', [
                    'plugin' => $id,
                    'path' => $path,
                ]);
            }
        }

        foreach ($runtimeEntryClasses as $entryClass => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            foreach ($ids as $id) {
                if (! in_array($id, $conflictingIds, true)) {
                    $conflictingIds[] = $id;
                }

                Log::warning('Runtime plugin skipped because another runtime plugin uses the same entry class.', [
                    'plugin' => $id,
                    'entry_class' => $entryClass,
                ]);
            }
        }

        return array_values(array_unique($conflictingIds));
    }

    private function readEntryClass(string $path): string
    {
        $manifestPath = $path.'/manifest.json';
        if (! is_file($manifestPath) || is_link($manifestPath)) {
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

    private function canonicalClassName(string $class): string
    {
        return strtolower(ltrim(trim($class), '\\'));
    }
}
