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
                ->first(['owner_property_id', 'scope']);

            if (! $amenity || ! $amenity->scope->allowsProperty()) {
                throw new InvalidArgumentException('This amenity is not available as a property facility.');
            }

            if ($amenity->owner_property_id !== null && (int) $amenity->owner_property_id !== (int) $pivot->property_id) {
                throw new InvalidArgumentException('Property-owned amenities may only be attached to their owner property.');
            }
        });
    }
}
