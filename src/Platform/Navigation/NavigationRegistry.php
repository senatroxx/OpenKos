<?php

namespace OpenKOS\Platform\Navigation;

use Illuminate\Contracts\Support\Arrayable;

class NavigationRegistry implements Arrayable
{
    /** @var array<string, array<int, NavigationItem>> */
    private array $groups = [];

    public function registerItem(NavigationItem $item, string $group = 'main'): static
    {
        $this->groups[$group][] = $item;

        return $this;
    }

    /**
     * @return array<int, NavigationItem>
     */
    public function items(?string $group = null): array
    {
        if ($group !== null) {
            return $this->groups[$group] ?? [];
        }

        return array_merge(...array_values($this->groups) ?: [[]]);
    }

    public function toArray(): array
    {
        return array_map(
            fn (array $items) => array_map(fn (NavigationItem $item) => $item->toArray(), $items),
            $this->groups,
        );
    }
}
