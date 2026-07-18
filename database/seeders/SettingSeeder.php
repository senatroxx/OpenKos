<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('site_name', 'OpenKOS');
        Setting::set('country_code', 'ID');
        Setting::set('locale', 'id');
        Setting::set('currency', 'IDR');
        Setting::set('timezone', 'Asia/Jakarta');
        Setting::set('lease_id_prefix', 'LSX');
        Setting::set('invoice_id_prefix', 'INV');
    }
}
