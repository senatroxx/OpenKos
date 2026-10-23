<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin() || $user->properties()->exists();
    }

    public function update(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }
}
