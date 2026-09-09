<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Voided => 'Voided',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
