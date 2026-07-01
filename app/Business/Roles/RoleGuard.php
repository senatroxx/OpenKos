<?php

namespace App\Business\Roles;

use App\Models\Role;

class RoleGuard
{
    public function isSystem(Role $role): bool
    {
        return $role->is_system;
    }

    public function ensureNotSystem(Role $role): void
    {
        abort_if($this->isSystem($role), 403, __('System roles cannot be modified.'));
    }
}
