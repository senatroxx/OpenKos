<?php

namespace OpenKOS\Platform\Workspace;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * Scoped registrar for one workspace (e.g. 'property', 'lease', 'tenant').
 */
class Workspace implements Arrayable
{
    /** @var array<string, WorkspaceTab> */
    private array $tabs = [];

    public function __construct(public readonly string $name) {}

    public function registerTab(WorkspaceTab $tab): static
    {
        if (isset($this->tabs[$tab->key])) {
            throw new InvalidArgumentException("Tab [{$tab->key}] is already registered on the [{$this->name}] workspace.");
        }

        $this->tabs[$tab->key] = $tab;

        return $this;
    }

    /**
     * @return array<string, WorkspaceTab>
     */
    public function tabs(): array
    {
        return $this->tabs;
    }

    public function toArray(): array
    {
        return array_values(array_map(fn (WorkspaceTab $tab) => $tab->toArray(), $this->tabs));
    }
}
