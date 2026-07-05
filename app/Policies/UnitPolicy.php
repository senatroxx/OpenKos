<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->properties->contains($unit->property_id);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->properties->contains($unit->property_id);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->properties->contains($unit->property_id);
    }
}
