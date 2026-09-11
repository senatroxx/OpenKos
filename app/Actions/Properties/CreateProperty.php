<?php

namespace App\Actions\Properties;

use App\Models\Property;
use App\Models\User;

final class CreateProperty
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, array $attributes): Property
    {
        $property = Property::create($attributes);
        $property->users()->syncWithoutDetaching([$actor->id]);

        return $property;
    }
}
