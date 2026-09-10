<?php

namespace App\Enums;

enum UtilityMeterType: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Custom = 'custom';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
