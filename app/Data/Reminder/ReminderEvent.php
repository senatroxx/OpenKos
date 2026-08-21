<?php

namespace App\Data\Reminder;

use App\Enums\ReminderType;
use App\Models\Invoice;
use App\Models\Lease;

class ReminderEvent
{
    public function __construct(
        public readonly Lease $lease,
        public readonly ReminderType $type,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $dueDate,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?int $overdueDays = null,
        public readonly ?Invoice $invoice = null,
    ) {}
}
