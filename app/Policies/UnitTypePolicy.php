<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;

class UnitTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UnitType $unitType): bool
    {
        return $user->canAccessProperty($unitType->property_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UnitType $unitType): bool
    {
        return $user->canAccessProperty($unitType->property_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UnitType $unitType): bool
    {
        return $this->update($user, $unitType);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UnitType $unitType): bool
    {
        return $this->update($user, $unitType);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UnitType $unitType): bool
    {
        return $this->update($user, $unitType);
    }
}
