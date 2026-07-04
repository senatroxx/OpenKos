<?php

namespace OpenKOS\Platform\Plugin;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Metadata every plugin declares. Drives boot ordering (dependencies),
 * compatibility gating (coreVersion), and discovery/UI (id, name, version).
 */
final readonly class PluginManifest implements Arrayable
{
    /**
     * @param  string  $id  unique, vendor-namespaced, e.g. 'openkos/whatsapp'
     * @param  string  $coreVersion  constraint against config('platform.version'): '*', 'x.y.z', or '^x.y'
     * @param  array<int, string>  $dependencies  ids of plugins that must load first
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description = '',
        public string $coreVersion = '*',
        public array $dependencies = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'core_version' => $this->coreVersion,
            'dependencies' => $this->dependencies,
        ];
    }
}
