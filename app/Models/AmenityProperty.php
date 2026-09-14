<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use InvalidArgumentException;

class AmenityProperty extends Pivot
{
    protected $table = 'amenity_property';

    public $incrementing = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (AmenityProperty $pivot): void {
            $amenity = Amenity::query()
                ->whereKey($pivot->amenity_id)
                ->first(['scope', 'is_active']);

            if (! $amenity || ! $amenity->scope->allowsProperty()) {
                throw new InvalidArgumentException('This amenity is not available as a property facility.');
            }

            if (! $amenity->is_active) {
                throw new InvalidArgumentException('Inactive amenities cannot be newly assigned.');
            }
        });
    }
}
