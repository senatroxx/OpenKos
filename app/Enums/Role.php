<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
