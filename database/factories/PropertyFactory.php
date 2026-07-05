<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $region = Region::inRandomOrder()->first() ?? Region::factory()->create();

        return [
            'name' => fake()->company(),
            'type' => 'boarding_house',
            'address' => fake()->address(),
            'region_id' => $region->id,
            'city_id' => City::where('region_id', $region->id)->inRandomOrder()->first()?->id
                ?? City::factory()->for($region)->create()->id,
            'postal_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
