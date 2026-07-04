<?php

namespace OpenKOS\Platform\Workspace;

use Illuminate\Contracts\Support\Arrayable;

class WorkspaceRegistry implements Arrayable
{
    /** @var array<string, Workspace> */
    private array $workspaces = [];

    public function for(string $name): Workspace
    {
        return $this->workspaces[$name] ??= new Workspace($name);
    }

    /**
     * @return array<string, Workspace>
     */
    public function all(): array
    {
        return $this->workspaces;
    }

    public function toArray(): array
    {
        return array_map(fn (Workspace $workspace) => $workspace->toArray(), $this->workspaces);
    }
}
