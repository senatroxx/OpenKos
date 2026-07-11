<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(100_000, 1_000_000),
        ];
    }
}
