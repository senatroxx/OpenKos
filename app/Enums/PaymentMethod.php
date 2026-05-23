<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Transfer => 'Bank Transfer',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
