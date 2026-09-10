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
            $ownerPropertyId = Amenity::query()
                ->whereKey($pivot->amenity_id)
                ->value('owner_property_id');
            $unitTypePropertyId = UnitType::query()
                ->whereKey($pivot->unit_type_id)
                ->value('property_id');

            if ($ownerPropertyId !== null && (int) $ownerPropertyId !== (int) $unitTypePropertyId) {
                throw new InvalidArgumentException('Property-owned amenities may only be attached to Unit Types from their owner property.');
            }
        });
    }
}
