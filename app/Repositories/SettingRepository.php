<?php

namespace App\Repositories;

use App\Data\Settings\SettingUpdateData;
use App\Models\Setting;

class SettingRepository
{
    public function update(array $data): SettingUpdateData
    {
        $original = [];

        foreach ($data as $key => $value) {
            $original[$key] = Setting::get($key);
            Setting::set($key, $value, $this->resolveType($key, $value));
        }

        return new SettingUpdateData(
            values: $data,
            original: $original,
            group: 'core',
        );
    }

    private function resolveType(string $key, mixed $value): string
    {
        return match (true) {
            $key === 'mail_password' => 'encrypted',
            $key === 'whatsapp_config' => 'encrypted:array',
            $key === 'reminder_overdue_intervals', $key === 'reminder_channels' => 'array',
            $key === 'reminder_enabled' => 'boolean',
            $key === 'reminder_days_before', $key === 'mail_port' => 'integer',
            default => 'string',
        };
    }
}
