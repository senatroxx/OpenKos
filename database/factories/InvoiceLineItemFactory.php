<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'type' => 'rent',
            'description' => 'Rent',
            'amount' => fake()->numberBetween(500_000, 3_000_000),
        ];
    }
}
