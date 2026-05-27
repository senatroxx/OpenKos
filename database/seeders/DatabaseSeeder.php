<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(RegionAndCitySeeder::class);
        $this->call(TenantSeeder::class);
        $this->call(PropertyAndRoomSeeder::class);
    }
}
