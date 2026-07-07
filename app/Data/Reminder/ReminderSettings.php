<?php

namespace App\Data\Reminder;

class ReminderSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly int $daysBefore,
        public readonly array $overdueIntervals,
    ) {}
}
