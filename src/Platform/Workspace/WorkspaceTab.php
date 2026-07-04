<?php

namespace OpenKOS\Platform\Workspace;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A tab on an entity workspace page. `key` maps to the frontend
 * PluginRegion slot `workspace-tab-{key}` (entity-workspace-layout.tsx).
 */
final readonly class WorkspaceTab implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $permission = null,
        public array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'permission' => $this->permission,
            'meta' => $this->meta,
        ];
    }
}
