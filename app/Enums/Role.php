<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
