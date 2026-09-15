<?php

namespace App\Actions\Properties;

use App\Enums\PropertyRentalMode;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;

final class CreateProperty
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, array $attributes): Property
    {
        if (! array_key_exists('rental_mode', $attributes) || $attributes['rental_mode'] === null || $attributes['rental_mode'] === '') {
            $attributes['rental_mode'] = PropertyType::query()
                ->where('slug', $attributes['type'] ?? 'boarding_house')
                ->value('default_rental_mode') ?? PropertyRentalMode::Unit->value;
        }

        $property = Property::create($attributes);
        $property->users()->syncWithoutDetaching([$actor->id]);

        return $property;
    }
}
