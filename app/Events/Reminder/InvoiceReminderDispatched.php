<?php

namespace App\Events\Reminder;

use App\Data\Reminder\ReminderEvent;
use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceReminderDispatched
{
    use Dispatchable;

    public function __construct(
        public readonly ReminderEvent $event,
        public readonly ?Invoice $invoice = null,
    ) {}
}
