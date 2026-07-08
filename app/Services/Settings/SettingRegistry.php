<?php

namespace App\Services\Settings;

class SettingRegistry
{
    public function get(string $key): ?array
    {
        $all = config('settings');

        return $all[$key] ?? null;
    }

    public function all(): array
    {
        return config('settings') ?? [];
    }
}
