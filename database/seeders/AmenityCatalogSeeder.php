<?php

namespace Database\Seeders;

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Models\Amenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AmenityCatalogSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, icon: AmenityIcon|null, scope: AmenityScope}>
     */
    private const CATALOG = [
        ['name' => 'Air conditioning', 'icon' => AmenityIcon::Snowflake, 'scope' => AmenityScope::UnitType],
        ['name' => 'Wi-Fi', 'icon' => AmenityIcon::Wifi, 'scope' => AmenityScope::Both],
        ['name' => 'TV', 'icon' => AmenityIcon::Tv, 'scope' => AmenityScope::UnitType],
        ['name' => 'Private bathroom', 'icon' => AmenityIcon::ShowerHead, 'scope' => AmenityScope::UnitType],
        ['name' => 'Water heater', 'icon' => null, 'scope' => AmenityScope::UnitType],
        ['name' => 'Kitchen', 'icon' => AmenityIcon::CookingPot, 'scope' => AmenityScope::Both],
        ['name' => 'Refrigerator', 'icon' => null, 'scope' => AmenityScope::UnitType],
        ['name' => 'Balcony', 'icon' => null, 'scope' => AmenityScope::UnitType],
        ['name' => 'Wardrobe', 'icon' => null, 'scope' => AmenityScope::UnitType],
        ['name' => 'Desk', 'icon' => null, 'scope' => AmenityScope::UnitType],
        ['name' => 'Parking', 'icon' => AmenityIcon::Car, 'scope' => AmenityScope::Property],
        ['name' => 'Pool', 'icon' => AmenityIcon::Waves, 'scope' => AmenityScope::Property],
        ['name' => 'Security', 'icon' => AmenityIcon::ShieldCheck, 'scope' => AmenityScope::Property],
        ['name' => 'Gym', 'icon' => AmenityIcon::Dumbbell, 'scope' => AmenityScope::Property],
        ['name' => 'Shared Kitchen', 'icon' => AmenityIcon::CookingPot, 'scope' => AmenityScope::Property],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $amenity) {
            $record = Amenity::query()
                ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$amenity['name']])
                ->first();

            if (! $record) {
                $record = Amenity::create([
                    'name' => $amenity['name'],
                    'slug' => Str::slug($amenity['name']),
                    'icon' => $amenity['icon'],
                    'scope' => $amenity['scope'],
                    'is_active' => true,
                ]);
            }

            $record->update([
                'scope' => $amenity['scope'],
                'icon' => $amenity['icon'],
                'is_active' => true,
            ]);
        }
    }
}
