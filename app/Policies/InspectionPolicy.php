<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $user->canAccessProperty($inspection->property_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return ! $inspection->isCompleted()
            && $user->canAccessProperty($inspection->property_id);
    }

    public function complete(User $user, Inspection $inspection): bool
    {
        return ! $inspection->isCompleted()
            && $user->canAccessProperty($inspection->property_id);
    }
}
