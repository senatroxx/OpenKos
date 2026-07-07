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
            Setting::set($key, $value);
        }

        return new SettingUpdateData(
            values: $data,
            original: $original,
            group: 'core',
        );
    }
}
