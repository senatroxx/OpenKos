<?php

namespace App\Services\Settings;

class SettingRegistry
{
    public function get(string $key): ?array
    {
        return config("settings.{$key}");
    }

    public function all(): array
    {
        return config('settings') ?? [];
    }
}
