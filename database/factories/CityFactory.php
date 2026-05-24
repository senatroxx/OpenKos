<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->city(),
        ];
    }
}
