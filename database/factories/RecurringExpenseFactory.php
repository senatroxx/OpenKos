<?php

namespace Database\Factories;

use App\Enums\BillingUnit;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
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
            'amount' => '500000.00',
            'currency' => 'IDR',
            'vendor' => fake()->company(),
            'description' => fake()->sentence(),
            'billing_interval' => 1,
            'billing_unit' => BillingUnit::Month,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'next_due_on' => now()->startOfMonth()->toDateString(),
            'paused_at' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'paused_at' => now(),
        ]);
    }
}
