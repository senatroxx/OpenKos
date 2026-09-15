<?php

namespace App\Enums;

enum InspectionItemCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Damaged => 'Damaged',
            self::NotApplicable => 'Not applicable',
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
