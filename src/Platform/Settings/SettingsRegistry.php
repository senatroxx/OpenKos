<?php

namespace OpenKOS\Platform\Settings;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class SettingsRegistry implements Arrayable
{
    /** @var array<string, SettingsPage> */
    private array $pages = [];

    public function registerPage(SettingsPage $page): static
    {
        if (isset($this->pages[$page->key])) {
            throw new InvalidArgumentException("Settings page [{$page->key}] is already registered.");
        }

        $this->pages[$page->key] = $page;

        return $this;
    }

    /**
     * @return array<string, SettingsPage>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function toArray(): array
    {
        return array_values(array_map(fn (SettingsPage $page) => $page->toArray(), $this->pages));
    }
}
