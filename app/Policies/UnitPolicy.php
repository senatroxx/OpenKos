<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->canAccessProperty($unit->property_id);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->canAccessProperty($unit->property_id);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->canAccessProperty($unit->property_id);
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $this->delete($user, $unit);
    }
}
