<?php

namespace App\Business\Users;

use App\Enums\Role;
use App\Models\User;

class UserGuard
{
    public function isLastActiveOwner(User $user): bool
    {
        return $user->isOwner()
            && User::role(Role::Owner->value)->where('is_active', true)->whereKeyNot($user->id)->doesntExist();
    }
}
