<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(500_000, 3_000_000),
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'payment_method' => 'cash',
            'reference_number' => null,
            'notes' => null,
            'status' => PaymentStatus::Confirmed,
            'confirmed_by' => null,
            'recorded_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
        ]);
    }
}
