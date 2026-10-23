<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Role as SpatieRole;
use App\Support\PermissionRoleReconciler;
use App\Support\RecommendedRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'name' => Role::Owner->value,
                'label' => Role::Owner->label(),
                'description' => 'System owner with full platform access.',
                'color' => '#111827',
                'is_system' => true,
            ],
            [
                'name' => Role::Admin->value,
                'label' => Role::Admin->label(),
                'description' => 'Administrative access to all apartments and properties.',
                'color' => '#2563eb',
                'is_system' => false,
            ],
            [
                'name' => Role::Manager->value,
                'label' => Role::Manager->label(),
                'description' => 'Property manager access scoped to assigned properties and units.',
                'color' => '#475569',
                'is_system' => false,
            ],
        ];

        foreach ($definitions as $definition) {
            $role = SpatieRole::firstOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'color' => $definition['color'],
                    'is_system' => $definition['is_system'],
                    'is_active' => true,
                ],
            );

            $role->label = $definition['label'];
            $role->description = $definition['description'];
            $role->color = $definition['color'];
            $role->is_system = $definition['is_system'];
            $role->is_active = true;
            $role->saveQuietly();
        }

        DB::statement('UPDATE roles SET is_system = true, is_active = true WHERE name = ?', [Role::Owner->value]);
        DB::statement('UPDATE roles SET is_active = true WHERE name IN (?, ?)', [Role::Admin->value, Role::Manager->value]);

        app(PermissionRoleReconciler::class)->reconcile();
    }
}
