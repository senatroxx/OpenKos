<?php

namespace OpenKOS\Platform\Permission;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Permissions declared by plugins. Registration is in-memory (idempotent);
 * `php artisan platform:permissions:sync` persists them into the Spatie
 * permissions table — plugins declare their own permissions without touching
 * core seeders.
 */
class PermissionRegistry implements Arrayable
{
    /** @var array<string, string> name => label */
    private array $permissions = [];

    public function register(string $name, ?string $label = null): static
    {
        $this->permissions[$name] = $label ?? $name;

        return $this;
    }

    /**
     * @return array<string, string> name => label
     */
    public function all(): array
    {
        return $this->permissions;
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    public function toArray(): array
    {
        $out = [];

        foreach ($this->permissions as $name => $label) {
            $out[] = ['name' => $name, 'label' => $label];
        }

        return $out;
    }
}
