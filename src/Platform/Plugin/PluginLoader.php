<?php

namespace OpenKOS\Platform\Plugin;

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
     * ponytail: supports '*', exact 'x.y.z', and caret '^x.y[.z]' only —
     * enough for a 0.x core. Swap in composer/semver if constraints get richer.
     */
    public function satisfies(string $version, string $constraint): bool
    {
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            return $this->caretSatisfies($version, ltrim($constraint, '^'));
        }

        return $version === $constraint;
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

    private function caretSatisfies(string $version, string $min): bool
    {
        $v = $this->parts($version);
        $m = $this->parts($min);

        // Semver caret: for 0.x the minor is the compatibility boundary.
        if ($m[0] === 0) {
            return $v[0] === 0 && $v[1] === $m[1] && ($v <=> $m) >= 0;
        }

        return $v[0] === $m[0] && ($v <=> $m) >= 0;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function parts(string $version): array
    {
        $p = array_map('intval', explode('.', $version.'.0.0'));

        return [$p[0], $p[1], $p[2]];
    }
}
