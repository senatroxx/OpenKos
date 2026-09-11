<?php

namespace Database\Seeders;

use App\Enums\AmenityScope;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenityCatalogSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, scope: AmenityScope}>
     */
    private const CATALOG = [
        ['name' => 'Air conditioning', 'scope' => AmenityScope::UnitType],
        ['name' => 'Wi-Fi', 'scope' => AmenityScope::UnitType],
        ['name' => 'TV', 'scope' => AmenityScope::UnitType],
        ['name' => 'Private bathroom', 'scope' => AmenityScope::UnitType],
        ['name' => 'Water heater', 'scope' => AmenityScope::UnitType],
        ['name' => 'Kitchen', 'scope' => AmenityScope::UnitType],
        ['name' => 'Refrigerator', 'scope' => AmenityScope::UnitType],
        ['name' => 'Balcony', 'scope' => AmenityScope::UnitType],
        ['name' => 'Wardrobe', 'scope' => AmenityScope::UnitType],
        ['name' => 'Desk', 'scope' => AmenityScope::UnitType],
        ['name' => 'Parking', 'scope' => AmenityScope::Property],
        ['name' => 'Pool', 'scope' => AmenityScope::Property],
        ['name' => 'Security', 'scope' => AmenityScope::Property],
        ['name' => 'Gym', 'scope' => AmenityScope::Property],
        ['name' => 'Shared Kitchen', 'scope' => AmenityScope::Property],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $amenity) {
            Amenity::query()->updateOrCreate(
                [
                    'owner_property_id' => null,
                    'name' => $amenity['name'],
                ],
                [
                    'scope' => $amenity['scope'],
                    'is_active' => true,
                ],
            );
        }
    }
}
