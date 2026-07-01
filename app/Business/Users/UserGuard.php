<?php

namespace App\Business\Users;

use App\Enums\Role;
use App\Models\User;

class UserGuard
{
    public function isLastActiveOwner(User $user): bool
    {
        return $user->isOwner()
            && User::role(Role::Owner->value)->whereRaw('is_active is true')->whereKeyNot($user->id)->doesntExist();
    }
}
