<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use InvalidArgumentException;

class AmenityUnitType extends Pivot
{
    protected $table = 'amenity_unit_type';

    public $incrementing = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (AmenityUnitType $pivot): void {
            $amenity = Amenity::query()
                ->whereKey($pivot->amenity_id)
                ->first(['owner_property_id', 'scope']);
            $unitTypePropertyId = UnitType::query()
                ->whereKey($pivot->unit_type_id)
                ->value('property_id');

            if (! $amenity || ! $amenity->scope->allowsUnitType()) {
                throw new InvalidArgumentException('This amenity is not available for a Unit Type.');
            }

            if ($amenity->owner_property_id !== null && (int) $amenity->owner_property_id !== (int) $unitTypePropertyId) {
                throw new InvalidArgumentException('Property-owned amenities may only be attached to Unit Types from their owner property.');
            }
        });
    }
}
