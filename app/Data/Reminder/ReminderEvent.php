<?php

namespace App\Data\Reminder;

use App\Enums\ReminderType;
use App\Models\Lease;

class ReminderEvent
{
    public function __construct(
        public readonly Lease $lease,
        public readonly ReminderType $type,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $dueDate,
        public readonly int $amount,
        public readonly ?int $overdueDays = null,
    ) {}
}
