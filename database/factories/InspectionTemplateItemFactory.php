<?php

namespace Database\Factories;

use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionTemplateItem>
 */
class InspectionTemplateItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_template_id' => InspectionTemplate::factory(),
            'label' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'position' => 0,
        ];
    }
}
