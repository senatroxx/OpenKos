<?php

namespace OpenKOS\Platform\Settings;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class SettingsRegistry implements Arrayable
{
    /** @var array<string, SettingsPage> */
    private array $pages = [];

    /** @var array<string, SettingDefinition> */
    private array $settings = [];

    public function registerPage(SettingsPage $page): static
    {
        if (isset($this->pages[$page->key])) {
            throw new InvalidArgumentException("Settings page [{$page->key}] is already registered.");
        }

        $this->pages[$page->key] = $page;

        return $this;
    }

    public function registerSetting(SettingDefinition $definition): static
    {
        if (isset($this->settings[$definition->key])) {
            throw new InvalidArgumentException("Setting [{$definition->key}] is already registered.");
        }

        $this->settings[$definition->key] = $definition;

        return $this;
    }

    /** @return array<string, SettingsPage> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return array<string, SettingDefinition> */
    public function definitions(?string $page = null): array
    {
        if ($page === null) {
            return $this->settings;
        }

        return array_filter(
            $this->settings,
            fn (SettingDefinition $d) => $d->page === $page,
        );
    }

    /**
     * Serializes pages for the Inertia platform prop (backward-compatible).
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (SettingsPage $page) => $page->toArray(), $this->pages));
    }

    public function definitionsToArray(): array
    {
        return array_values(array_map(fn (SettingDefinition $def) => $def->toArray(), $this->settings));
    }
}
