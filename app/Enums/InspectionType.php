<?php

namespace App\Enums;

enum InspectionType: string
{
    case MoveIn = 'move_in';
    case MoveOut = 'move_out';
    case Periodic = 'periodic';

    public function label(): string
    {
        return match ($this) {
            self::MoveIn => 'Move-in',
            self::MoveOut => 'Move-out',
            self::Periodic => 'Periodic',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
