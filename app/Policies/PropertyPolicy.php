<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }
}
