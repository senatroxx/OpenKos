<?php

namespace App\Data\Reminder;

use App\Models\Setting;

class ReminderSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly int $daysBefore,
        public readonly array $overdueIntervals,
    ) {}

    public static function fromSetting(Setting $setting): self
    {
        return new self(
            enabled: $setting->reminder_enabled,
            daysBefore: $setting->reminder_days_before,
            overdueIntervals: $setting->reminder_overdue_intervals,
        );
    }
}
