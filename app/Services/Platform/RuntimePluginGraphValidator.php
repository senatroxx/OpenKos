<?php

namespace App\Services\Platform;

use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLoader;
use Throwable;

final class RuntimePluginGraphValidator
{
    public function __construct(private RuntimePluginArtifactValidator $validator) {}

    /**
     * @param  array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>  $plugins
     * @param  array<int, string>  $hostClasses
     * @return array{
     *     loadable: array<int, string>,
     *     issues: array<string, array{status: string, error: string}>
     * }
     */
    public function validate(array $plugins, array $hostClasses): array
    {
        [$hostIds] = $this->hostIdentity($hostClasses);
        $issues = [];
        $entryClasses = [];
        $hostEntryClasses = array_fill_keys(array_map(
            fn (string $class): string => $this->canonicalClassName($class),
            array_values(array_filter($hostClasses, 'is_string')),
        ), true);
        $entryClassNames = [];

        foreach ($plugins as $id => $plugin) {
            if (isset($plugin['status'], $plugin['error'])) {
                $issues[$id] = [
                    'status' => $plugin['status'],
                    'error' => $plugin['error'],
                ];

                continue;
            }

            $metadata = $plugin['metadata'];

            if (! is_array($metadata) || ($metadata['id'] ?? null) !== $id) {
                $this->addIssue($issues, $id, 'broken', 'Runtime plugin metadata does not match its managed identity.');

                continue;
            }

            if (is_string($metadata['entry_class'] ?? null)) {
                $canonicalEntryClass = $this->canonicalClassName($metadata['entry_class']);
                $entryClasses[$canonicalEntryClass][] = $id;
                $entryClassNames[$canonicalEntryClass] = $metadata['entry_class'];
            }

            if (
                isset($hostIds[$id])
                || isset($hostEntryClasses[$this->canonicalClassName((string) ($metadata['entry_class'] ?? ''))])
            ) {
                $this->addIssue($issues, $id, 'conflict', "Runtime plugin [{$id}] conflicts with a Composer or explicit plugin.");

                continue;
            }

            try {
                if (! (new PluginLoader)->satisfies(
                    (string) config('platform.version', '0.2.0'),
                    (string) ($metadata['core_version'] ?? '*'),
                )) {
                    $this->addIssue(
                        $issues,
                        $id,
                        'incompatible',
                        "Runtime plugin [{$id}] is incompatible with the current OpenKOS version.",
                    );
                }
            } catch (Throwable) {
                $this->addIssue(
                    $issues,
                    $id,
                    'incompatible',
                    "Runtime plugin [{$id}] declares an invalid OpenKOS version constraint.",
                );
            }
        }

        foreach ($entryClasses as $entryClass => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            foreach ($ids as $id) {
                $this->addIssue(
                    $issues,
                    $id,
                    'conflict',
                    "Runtime plugin [{$id}] conflicts with another runtime plugin entry class [{$entryClassNames[$entryClass]}].",
                );
            }
        }

        do {
            $changed = false;

            foreach ($plugins as $id => $plugin) {
                if (isset($issues[$id]) || ! $plugin['enabled'] || ! is_array($plugin['metadata'])) {
                    continue;
                }

                foreach ($plugin['metadata']['dependencies'] ?? [] as $dependency) {
                    if (isset($hostIds[$dependency])) {
                        continue;
                    }

                    if (! isset($plugins[$dependency])) {
                        $changed = $this->addIssue(
                            $issues,
                            $id,
                            'broken',
                            "Runtime plugin [{$id}] depends on missing plugin [{$dependency}].",
                        ) || $changed;

                        break;
                    }

                    if (! $plugins[$dependency]['enabled']) {
                        $changed = $this->addIssue(
                            $issues,
                            $id,
                            'broken',
                            "Runtime plugin [{$id}] depends on disabled plugin [{$dependency}].",
                        ) || $changed;

                        break;
                    }

                    if (isset($issues[$dependency])) {
                        $changed = $this->addIssue(
                            $issues,
                            $id,
                            'broken',
                            "Runtime plugin [{$id}] depends on unavailable plugin [{$dependency}].",
                        ) || $changed;

                        break;
                    }
                }
            }

            foreach ($this->cycleNodes($plugins, $issues, $hostIds) as $cycle) {
                $changed = $this->addIssue(
                    $issues,
                    $cycle,
                    'broken',
                    'Runtime plugin dependency cycle detected.',
                ) || $changed;
            }
        } while ($changed);

        $loadable = [];
        foreach ($plugins as $id => $plugin) {
            if ($plugin['enabled'] && ! isset($issues[$id])) {
                $loadable[] = $id;
            }
        }

        return ['loadable' => $loadable, 'issues' => $issues];
    }

    /**
     * @param  array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>  $plugins
     * @param  array<int, string>  $hostClasses
     */
    public function validateCandidate(array $metadata, bool $enabled, RuntimePluginStore $store, array $hostClasses): void
    {
        $plugins = $this->installedPlugins($store, $metadata['id']);

        $plugins[$metadata['id']] = [
            'metadata' => $metadata,
            'enabled' => $enabled,
        ];

        [$hostIds] = $this->hostIdentity($hostClasses);
        $result = $this->validate($plugins, $hostClasses);
        $idsToCheck = $enabled
            ? $this->dependencyClosure($metadata['id'], $plugins, $hostIds)
            : [$metadata['id']];

        foreach ($idsToCheck as $id) {
            if (isset($result['issues'][$id])) {
                $issue = $result['issues'][$id];

                if ($id === $metadata['id'] && $issue['status'] === 'conflict') {
                    throw new RuntimePluginConflictException($issue['error']);
                }

                throw new RuntimeException($issue['error']);
            }
        }
    }

    /**
     * @param  array<int, string>  $hostClasses
     */
    public function canForceRecover(string $id, RuntimePluginStore $store, array $hostClasses): bool
    {
        $plugins = $this->installedPlugins($store);
        $result = $this->validate($plugins, $hostClasses);

        if (isset($result['issues'][$id])) {
            return true;
        }

        $dependants = $this->dependantsFromPlugins($id, $plugins, $hostClasses);

        return $dependants !== [] && ! array_diff($dependants, array_keys($result['issues']));
    }

    /**
     * @param  array<int, string>  $hostClasses
     * @return array<int, string>
     */
    public function enabledDependants(string $id, RuntimePluginStore $store, array $hostClasses): array
    {
        return $this->dependantsFromPlugins($id, $this->installedPlugins($store), $hostClasses);
    }

    /**
     * @param  array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>  $plugins
     * @param  array<int, string>  $hostClasses
     * @return array<int, string>
     */
    private function dependantsFromPlugins(string $id, array $plugins, array $hostClasses): array
    {
        [$hostIds, $validHostClasses] = $this->hostIdentity($hostClasses);

        if (isset($hostIds[$id])) {
            return [];
        }

        $dependants = [];

        foreach ($plugins as $packageId => $plugin) {
            if ($packageId === $id || ! $plugin['enabled'] || ! is_array($plugin['metadata'])) {
                continue;
            }

            if (in_array($id, $plugin['metadata']['dependencies'], true)) {
                $dependants[] = $packageId;
            }
        }

        foreach ($this->hostMetadata($validHostClasses) as $metadata) {
            if (in_array($id, $metadata['dependencies'], true)) {
                $dependants[] = $metadata['id'];
            }
        }

        sort($dependants, SORT_STRING);

        return array_values(array_unique($dependants));
    }

    /**
     * @param  array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>  $plugins
     * @param  array<string, bool>  $hostIds
     * @return array<int, string>
     */
    private function dependencyClosure(string $id, array $plugins, array $hostIds): array
    {
        $closure = [];
        $pending = [$id];

        while ($pending !== []) {
            $current = array_pop($pending);

            if (! is_string($current) || isset($closure[$current])) {
                continue;
            }

            $closure[$current] = true;
            $metadata = $plugins[$current]['metadata'] ?? null;

            if (! is_array($metadata)) {
                continue;
            }

            foreach ($metadata['dependencies'] ?? [] as $dependency) {
                if (! isset($hostIds[$dependency]) && isset($plugins[$dependency])) {
                    $pending[] = $dependency;
                }
            }
        }

        return array_keys($closure);
    }

    /**
     * @return array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>
     */
    private function installedPlugins(RuntimePluginStore $store, ?string $exceptId = null): array
    {
        $state = $store->readState();
        $plugins = [];

        foreach ($store->installedPackages() as $id => $path) {
            if ($id === $exceptId) {
                continue;
            }

            $enabled = $state[$id]['enabled'] ?? false;

            try {
                $plugins[$id] = [
                    'metadata' => $this->validator->inspectStaticMetadata($path, $id),
                    'enabled' => $enabled,
                ];
            } catch (Throwable $exception) {
                $plugins[$id] = [
                    'metadata' => null,
                    'enabled' => $enabled,
                    'status' => $this->validationStatus($exception),
                    'error' => $this->validationError($exception),
                ];
            }
        }

        return $plugins;
    }

    /**
     * @param  array<string, array{
     *     metadata: array<string, mixed>|null,
     *     enabled: bool,
     *     status?: string,
     *     error?: string
     * }>  $plugins
     * @param  array<string, array{status: string, error: string}>  $issues
     * @param  array<string, bool>  $hostIds
     * @return array<int, string>
     */
    private function cycleNodes(array $plugins, array $issues, array $hostIds): array
    {
        $cycles = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $id, array $path) use (&$visit, &$cycles, &$visiting, &$visited, $plugins, $issues, $hostIds): void {
            if (isset($visited[$id]) || isset($issues[$id]) || ! ($plugins[$id]['enabled'] ?? false)) {
                return;
            }

            if (isset($visiting[$id])) {
                $start = array_search($id, $path, true);
                if (is_int($start)) {
                    foreach (array_slice($path, $start) as $cycleId) {
                        $cycles[$cycleId] = true;
                    }
                }

                return;
            }

            $visiting[$id] = true;
            $path[] = $id;

            foreach ($plugins[$id]['metadata']['dependencies'] ?? [] as $dependency) {
                if (isset($hostIds[$dependency]) || ! isset($plugins[$dependency])) {
                    continue;
                }

                $visit($dependency, $path);
            }

            unset($visiting[$id]);
            $visited[$id] = true;
        };

        foreach (array_keys($plugins) as $id) {
            $visit($id, []);
        }

        return array_keys($cycles);
    }

    /**
     * @param  array<int, string>  $hostClasses
     * @return array{0: array<string, bool>, 1: array<int, string>}
     */
    private function hostIdentity(array $hostClasses): array
    {
        $validClasses = [];
        $ids = [];

        foreach (array_values(array_unique(array_filter($hostClasses, 'is_string'))) as $class) {
            if (! class_exists($class) || ! is_a($class, Plugin::class, true)) {
                continue;
            }

            try {
                $metadata = app()->make($class)->manifest();
            } catch (Throwable) {
                continue;
            }

            $validClasses[] = $class;
            $ids[$metadata->id] = true;
        }

        return [$ids, $validClasses];
    }

    private function canonicalClassName(string $class): string
    {
        return strtolower(ltrim(trim($class), '\\'));
    }

    /**
     * @param  array<int, string>  $hostClasses
     * @return array<int, array{id: string, dependencies: array<int, string>}>
     */
    private function hostMetadata(array $hostClasses): array
    {
        $metadata = [];

        foreach ($hostClasses as $class) {
            if (! class_exists($class) || ! is_a($class, Plugin::class, true)) {
                continue;
            }

            try {
                $manifest = app()->make($class)->manifest();
            } catch (Throwable) {
                continue;
            }

            $metadata[] = [
                'id' => $manifest->id,
                'dependencies' => $manifest->dependencies,
            ];
        }

        return $metadata;
    }

    /** @param array<string, array{status: string, error: string}> $issues */
    private function addIssue(array &$issues, string $id, string $status, string $error): bool
    {
        if (isset($issues[$id])) {
            return false;
        }

        $issues[$id] = ['status' => $status, 'error' => $error];

        return true;
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
            return 'Manifest plugin ID does not match installed package identity.';
        }

        return $this->validationStatus($exception) === 'incompatible'
            ? 'This plugin is not compatible with the current OpenKOS or PHP installation.'
            : 'This plugin is incomplete or failed validation.';
    }
}
