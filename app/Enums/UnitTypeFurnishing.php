<?php

namespace App\Enums;

enum UnitTypeFurnishing: string
{
    case Unfurnished = 'unfurnished';
    case SemiFurnished = 'semi-furnished';
    case Furnished = 'furnished';

    public function label(): string
    {
        return match ($this) {
            self::Unfurnished => 'Unfurnished',
            self::SemiFurnished => 'Semi-furnished',
            self::Furnished => 'Furnished',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
