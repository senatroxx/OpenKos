<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Role;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

final class PermissionRoleReconciler
{
    /**
     * @var array<int, string>
     */
    private const TRANSFER_PERMISSIONS = [
        'properties.import',
        'properties.export',
        'units.import',
        'units.export',
        'tenants.import',
        'tenants.export',
        'tenants.export_sensitive',
        'unit-rates.import',
        'unit-rates.export',
    ];

    public function reconcile(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::all() as $permission) {
            SpatiePermission::findOrCreate($permission->value);
        }

        $this->syncOwner();

        foreach (RecommendedRoles::all() as $definition) {
            $transferPermissions = array_values(array_intersect(
                $definition['permissions'],
                self::TRANSFER_PERMISSIONS,
            ));

            if ($transferPermissions === []) {
                continue;
            }

            $role = Role::query()
                ->where('name', $definition['name'])
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                continue;
            }

            $existing = $role->permissions()->pluck('name')->all();
            $baseline = array_diff($definition['permissions'], $transferPermissions);

            if (array_diff($baseline, $existing) !== []) {
                continue;
            }

            $role->givePermissionTo($transferPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncOwner(): void
    {
        $owner = Role::query()
            ->where('name', 'owner')
            ->where('guard_name', 'web')
            ->first();

        if ($owner !== null) {
            $owner->syncPermissions(array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::forRole(RoleEnum::Owner),
            ));
        }
    }
}
