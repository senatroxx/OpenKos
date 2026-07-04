<?php

namespace OpenKOS\Platform\Dashboard;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class DashboardRegistry implements Arrayable
{
    /** @var array<string, DashboardPage> */
    private array $pages = [];

    public function registerPage(DashboardPage $page): static
    {
        if (isset($this->pages[$page->key])) {
            throw new InvalidArgumentException("Dashboard page [{$page->key}] is already registered.");
        }

        $this->pages[$page->key] = $page;

        return $this;
    }

    /**
     * @return array<string, DashboardPage>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function toArray(): array
    {
        return array_values(array_map(fn (DashboardPage $page) => $page->toArray(), $this->pages));
    }
}
