<?php

namespace OpenKOS\Platform\Plugin;

use Composer\Semver\Semver;
use InvalidArgumentException;

/**
 * Validates plugin manifests against the running core version and their
 * declared dependencies, then returns them ordered so every plugin loads
 * after the plugins it depends on.
 */
class PluginLoader
{
    /**
     * @param  array<int, Plugin>  $plugins
     * @return array<int, Plugin>
     *
     * @throws InvalidArgumentException on duplicate id, incompatible core
     *                                  version, missing dependency, or cycle
     */
    public function prepare(array $plugins, string $coreVersion): array
    {
        $byId = [];

        foreach ($plugins as $plugin) {
            $id = $plugin->manifest()->id;

            if (isset($byId[$id])) {
                throw new InvalidArgumentException("Duplicate plugin id [{$id}].");
            }

            $byId[$id] = $plugin;
        }

        foreach ($byId as $id => $plugin) {
            $manifest = $plugin->manifest();

            if (! $this->satisfies($coreVersion, $manifest->coreVersion)) {
                throw new InvalidArgumentException(
                    "Plugin [{$id}] requires core {$manifest->coreVersion}, but core is {$coreVersion}.",
                );
            }

            foreach ($manifest->dependencies as $dependency) {
                if (! isset($byId[$dependency])) {
                    throw new InvalidArgumentException(
                        "Plugin [{$id}] depends on missing plugin [{$dependency}].",
                    );
                }
            }
        }

        return $this->sortByDependencies($byId);
    }

    /**
     * Uses Composer's own constraint engine, so a plugin's coreVersion means
     * exactly what the same constraint means in composer.json (^, ~, ranges,
     * ||, wildcards, …).
     */
    public function satisfies(string $version, string $constraint): bool
    {
        return $constraint === '' || Semver::satisfies($version, $constraint);
    }

    /**
     * @param  array<string, Plugin>  $byId
     * @return array<int, Plugin>
     */
    private function sortByDependencies(array $byId): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $id) use (&$visit, &$sorted, &$visiting, $byId): void {
            if (isset($sorted[$id])) {
                return;
            }

            if (isset($visiting[$id])) {
                throw new InvalidArgumentException("Circular plugin dependency involving [{$id}].");
            }

            $visiting[$id] = true;

            foreach ($byId[$id]->manifest()->dependencies as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$id]);
            $sorted[$id] = $byId[$id];
        };

        foreach (array_keys($byId) as $id) {
            $visit($id);
        }

        return array_values($sorted);
    }
}
