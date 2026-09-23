<?php

namespace Database\Factories;

use App\Enums\ApplicationTargetType;
use App\Models\Application;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'property_id' => Property::factory(),
            'target_type' => ApplicationTargetType::WholeProperty,
            'status' => 'new',
            'applicant_name' => fake()->name(),
            'applicant_email' => fake()->safeEmail(),
            'open_application_key' => (string) fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
