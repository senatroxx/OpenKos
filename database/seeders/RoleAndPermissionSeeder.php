<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permission::all() as $permission) {
            SpatiePermission::findOrCreate($permission->value);
        }

        foreach (Role::cases() as $role) {
            $spatieRole = SpatieRole::findOrCreate($role->value);

            if ($role !== Role::Owner) {
                $permissions = Permission::forRole($role);
                $spatieRole->syncPermissions(array_map(
                    fn (Permission $p) => $p->value,
                    $permissions,
                ));
            }
        }
    }
}
