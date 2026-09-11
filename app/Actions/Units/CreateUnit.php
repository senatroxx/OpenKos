<?php

namespace App\Actions\Units;

use App\Models\Property;
use App\Models\Unit;

final class CreateUnit
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Property $property, array $attributes): Unit
    {
        return $property->units()->create($attributes);
    }
}
