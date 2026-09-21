<?php

namespace App\Data\Reminder;

use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final readonly class ReminderInvoiceData
{
    public function __construct(
        public Invoice $invoice,
        public CarbonInterface $dueDate,
        public string $periodStart,
        public string $periodEnd,
        public string $amount,
        public string $currency,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            invoice: $invoice,
            dueDate: Carbon::parse($invoice->due_date)->startOfDay(),
            periodStart: $invoice->period_start->toDateString(),
            periodEnd: $invoice->period_end->toDateString(),
            amount: $invoice->outstanding,
            currency: $invoice->currency,
        );
    }
}
