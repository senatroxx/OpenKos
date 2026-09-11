<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Role as SpatieRole;
use App\Support\PermissionRoleReconciler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = SpatieRole::findOrCreate(Role::Owner->value);
        $ownerRole->label = Role::Owner->label();
        $ownerRole->saveQuietly();

        DB::statement('UPDATE roles SET is_system = true, is_active = true WHERE id = ?', [$ownerRole->id]);

        app(PermissionRoleReconciler::class)->reconcile();
    }
}
