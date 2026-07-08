<?php

namespace App\Services\Settings;

use App\Models\Setting;

class SettingManager
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingCaster $caster,
    ) {}

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->allWithDefaults();
        }

        $setting = Setting::where('key', $key)->first();

        if ($setting) {
            return $setting->resolveValue();
        }

        $def = $this->registry->get($key);

        return $def['default'] ?? null;
    }

    public function set(string $key, mixed $value, ?string $cast = null): Setting
    {
        $cast ??= $this->registry->get($key)['cast'] ?? 'string';

        $stored = $this->caster->serialize($value, $cast);

        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $cast],
        );
    }

    public function some(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    private function allWithDefaults(): array
    {
        $defaults = [];
        foreach ($this->registry->all() as $key => $def) {
            $defaults[$key] = $this->caster->serialize($def['default'], $def['cast']);
        }

        return array_merge($defaults, Setting::pluck('value', 'key')->all());
    }
}
