<?php

namespace OpenKOS\Platform\Navigation;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Keys in toArray() mirror the frontend NavItem type
 * (resources/js/types/navigation.ts), plus `permission` for visibility gating.
 */
final readonly class NavigationItem implements Arrayable
{
    /**
     * @param  ?string  $icon  lucide icon name, resolved to a component client-side
     * @param  ?string  $permission  spatie permission string, e.g. 'properties.view'
     * @param  array<int, NavigationItem>  $children
     */
    public function __construct(
        public string $title,
        public ?string $href = null,
        public ?string $icon = null,
        public ?string $permission = null,
        public array $children = [],
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'href' => $this->href,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'children' => array_map(fn (NavigationItem $child) => $child->toArray(), $this->children),
        ];
    }
}
