<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RegionAndCitySeeder extends Seeder
{
    public function run(): void
    {
        City::query()->delete();
        Region::query()->delete();

        $provinces = json_decode(
            File::get(database_path('data/provinces.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $regencies = json_decode(
            File::get(database_path('data/regencies.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $regenciesByProvinceId = [];

        foreach ($regencies as $regency) {
            $regenciesByProvinceId[$regency['province_id']][] = $regency;
        }

        foreach ($provinces as $province) {
            $region = Region::create([
                'country_code' => 'ID',
                'name' => $province['province'],
            ]);

            $cities = $regenciesByProvinceId[$province['id']] ?? [];

            foreach ($cities as $city) {
                City::create([
                    'region_id' => $region->id,
                    'name' => trim(($city['type'] ?? '').' '.$city['regency']),
                ]);
            }
        }
    }
}
