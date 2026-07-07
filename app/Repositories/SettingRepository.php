<?php

namespace App\Repositories;

use App\Data\Settings\SettingUpdateData;
use App\Models\Setting;

class SettingRepository
{
    public function get(): Setting
    {
        return Setting::get();
    }

    public function update(array $data): SettingUpdateData
    {
        $setting = Setting::get();
        $original = $setting->only(array_keys($data));

        $setting->update($data);

        return new SettingUpdateData(
            values: $setting->only(array_keys($data)),
            original: $original,
            group: 'core',
        );
    }
}
