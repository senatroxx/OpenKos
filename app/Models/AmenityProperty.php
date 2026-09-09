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
            $ownerPropertyId = Amenity::query()
                ->whereKey($pivot->amenity_id)
                ->value('owner_property_id');

            if ($ownerPropertyId !== null && (int) $ownerPropertyId !== (int) $pivot->property_id) {
                throw new InvalidArgumentException('Property-owned amenities may only be attached to their owner property.');
            }
        });
    }
}
