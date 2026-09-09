<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => '100.00',
            'currency' => 'IDR',
            'expense_date' => now()->toDateString(),
            'vendor' => fake()->company(),
            'description' => fake()->sentence(),
            'notes' => null,
            'reference' => fake()->bothify('EXP-####'),
            'status' => ExpenseStatus::Active,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Voided,
            'voided_at' => now(),
            'void_reason' => 'Duplicate entry',
        ]);
    }
}
