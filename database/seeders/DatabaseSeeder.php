<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AmenityCatalogSeeder::class,
            SettingSeeder::class,
            RegionAndCitySeeder::class,
            OwnerSeeder::class,
            TenantSeeder::class,
            PropertyAndUnitSeeder::class,
            DemoUsersSeeder::class,
            LeaseSeeder::class,
            MaintenanceSeeder::class,
        ]);
    }
}
