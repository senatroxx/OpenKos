<?php

namespace App\Enums;

/**
 * Domain classification of a property. Defaults to Kos; other types exist so
 * business logic can branch on the type instead of assuming every property is
 * a boarding house.
 */
enum PropertyType: string
{
    case Kos = 'kos';
    case Apartment = 'apartment';
    case Villa = 'villa';
    case Hostel = 'hostel';
    case Hotel = 'hotel';

    public function label(): string
    {
        return match ($this) {
            self::Kos => 'Kos',
            self::Apartment => 'Apartment',
            self::Villa => 'Villa',
            self::Hostel => 'Hostel',
            self::Hotel => 'Hotel',
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
