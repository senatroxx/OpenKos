<?php

namespace App\Enums;

enum BillingStrategy: string
{
    case Advance = 'advance';
    case Arrears = 'arrears';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Advance',
            self::Arrears => 'Arrears',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
