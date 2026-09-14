<?php

namespace App\Enums;

enum AmenityScope: string
{
    case Property = 'property';
    case UnitType = 'unit_type';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Property => 'Property',
            self::UnitType => 'Unit Type',
            self::Both => 'Both',
        };
    }

    public function allowsProperty(): bool
    {
        return $this === self::Property || $this === self::Both;
    }

    public function allowsUnitType(): bool
    {
        return $this === self::UnitType || $this === self::Both;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
