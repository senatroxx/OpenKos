<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-6 months', 'now');
        $periodStart = Carbon::instance($periodStart)->startOfMonth();

        return [
            'lease_id' => Lease::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodStart->copy()->endOfMonth(),
            'due_date' => $periodStart->copy()->setDay(5),
            'status' => InvoiceStatus::Pending,
            'total' => fake()->numberBetween(500_000, 3_000_000),
            'amount_paid' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $attributes['total'],
        ]);
    }
}
