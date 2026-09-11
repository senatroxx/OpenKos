<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Support\PermissionRoleReconciler;
use App\Support\RecommendedRoles;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Permission as SpatiePermission;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('reconciles existing default roles idempotently without changing custom roles', function () {
    $definition = collect(RecommendedRoles::all())->firstWhere('name', 'admin');
    $transferPermissions = [
        Permission::PropertiesImport->value,
        Permission::PropertiesExport->value,
        Permission::UnitsImport->value,
        Permission::UnitsExport->value,
        Permission::TenantsImport->value,
        Permission::TenantsExport->value,
        Permission::UnitRatesImport->value,
        Permission::UnitRatesExport->value,
        Permission::ExpensesImport->value,
        Permission::ExpensesExport->value,
    ];

    $admin = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'label' => 'Admin',
        'is_system' => true,
        'is_active' => true,
    ]);
    $admin->syncPermissions(array_values(array_diff($definition['permissions'], $transferPermissions)));

    $custom = Role::create([
        'name' => 'custom-role',
        'guard_name' => 'web',
        'label' => 'Custom role',
        'is_system' => false,
        'is_active' => true,
    ]);
    $custom->givePermissionTo(Permission::UnitsView->value);

    app(PermissionRoleReconciler::class)->reconcile();

    expect($admin->fresh()->hasPermissionTo(Permission::PropertiesImport->value))->toBeTrue()
        ->and($admin->fresh()->hasPermissionTo(Permission::UnitRatesExport->value))->toBeTrue()
        ->and($admin->fresh()->hasPermissionTo(Permission::ExpensesImport->value))->toBeTrue()
        ->and($admin->fresh()->hasPermissionTo(Permission::ExpensesExport->value))->toBeTrue()
        ->and($custom->fresh()->hasPermissionTo(Permission::UnitsView->value))->toBeTrue()
        ->and($custom->fresh()->hasPermissionTo(Permission::PropertiesImport->value))->toBeFalse();

    app(PermissionRoleReconciler::class)->reconcile();

    expect(SpatiePermission::query()
        ->where('name', Permission::PropertiesImport->value)
        ->count())->toBe(1);
});
