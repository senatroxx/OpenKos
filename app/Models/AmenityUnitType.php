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
                ->first(['scope', 'is_active']);

            if (! $amenity || ! $amenity->scope->allowsUnitType()) {
                throw new InvalidArgumentException('This amenity is not available for a Unit Type.');
            }

            if (! $amenity->is_active) {
                throw new InvalidArgumentException('Inactive amenities cannot be newly assigned.');
            }
        });
    }
}
