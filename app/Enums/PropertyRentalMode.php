<?php

namespace App\Enums;

enum PropertyRentalMode: string
{
    case Unit = 'unit';
    case WholeProperty = 'whole_property';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Individual units',
            self::WholeProperty => 'Whole property',
            self::Hybrid => 'Both',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Unit => 'Customers rent a unit or unit type.',
            self::WholeProperty => 'The entire property is the rentable offering.',
            self::Hybrid => 'The property can support both models.',
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
