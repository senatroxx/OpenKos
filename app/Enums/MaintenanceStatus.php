<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Reported = 'reported';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
