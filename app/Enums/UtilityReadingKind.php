<?php

namespace App\Enums;

enum UtilityReadingKind: string
{
    case Reading = 'reading';
    case Correction = 'correction';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
