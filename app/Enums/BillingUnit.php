<?php

namespace App\Enums;

enum BillingUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
