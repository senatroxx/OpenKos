<?php

namespace App\Events\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the generation engine creates an invoice. A domain event
 * plugins can subscribe to (e.g. payment gateways, tenant portal,
 * accounting) via Plugin::listens().
 */
class InvoiceGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
