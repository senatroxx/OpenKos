<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Role as SpatieRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permission::all() as $permission) {
            SpatiePermission::findOrCreate($permission->value);
        }

        $ownerRole = SpatieRole::findOrCreate(Role::Owner->value);
        $ownerRole->label = Role::Owner->label();
        $ownerRole->saveQuietly();

        DB::statement('UPDATE roles SET is_system = true, is_active = true WHERE id = ?', [$ownerRole->id]);

        $ownerRole->syncPermissions(array_map(
            fn (Permission $p) => $p->value,
            Permission::forRole(Role::Owner),
        ));
    }
}
