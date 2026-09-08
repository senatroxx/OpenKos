<?php

namespace Database\Factories;

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
            'invoice_id' => 0,
            'amount' => '0',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PaymentAllocation $allocation): void {
            $payment = Payment::query()->findOrFail($allocation->payment_id);

            $allocation->forceFill([
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
            ]);
        });
    }
}
