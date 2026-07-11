<?php

namespace App\Events\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceFullyPaid
{
    use Dispatchable;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
