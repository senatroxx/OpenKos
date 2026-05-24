<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'site_name' => 'OpenKOS',
            'country_code' => 'ID',
            'locale' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ];
    }
}
