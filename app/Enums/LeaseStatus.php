<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Renewed = 'renewed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Terminated => 'Terminated',
            self::Renewed => 'Renewed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
