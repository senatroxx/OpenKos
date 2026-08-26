<?php

namespace App\Repositories;

use App\Data\Settings\SettingUpdateData;
use App\Models\Setting;
use App\Services\Settings\InstallationCurrencySettings;
use Illuminate\Support\Facades\DB;

class SettingRepository
{
    public function __construct(
        private InstallationCurrencySettings $currencies,
    ) {}

    public function update(array $data): SettingUpdateData
    {
        return DB::transaction(function () use ($data) {
            if (array_intersect(array_keys($data), ['currency', 'supported_currencies']) !== []) {
                $this->currencies->lockForUpdate();
            }

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
        });
    }
}
