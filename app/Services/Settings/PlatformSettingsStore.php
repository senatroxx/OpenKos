<?php

namespace App\Services\Settings;

use Illuminate\Contracts\Container\Container;
use OpenKOS\Core\Contracts\SettingsStore;

class PlatformSettingsStore implements SettingsStore
{
    public function __construct(private Container $container) {}

    public function get(string $key): mixed
    {
        return $this->container->make(SettingManager::class)->get($key);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        $this->container->make(SettingManager::class)->set($key, $value, $type);
    }
}
