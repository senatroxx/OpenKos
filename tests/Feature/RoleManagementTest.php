<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('owner can view roles page', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->get(route('roles.index'))
            ->assertOk();
    });

    it('non-owner cannot view roles page', function () {
        $admin = User::factory()->admin()->create();

        $this->from(route('roles.index'))->actingAs($admin)
            ->get(route('roles.index'))
            ->assertForbidden();
    });

    it('staff cannot view roles page', function () {
        $staff = User::factory()->staff()->create();

        $this->from(route('roles.index'))->actingAs($staff)
            ->get(route('roles.index'))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('owner can create custom role with existing permissions', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'finance-staff',
                'label' => 'Finance Staff',
                'description' => 'Handles financials',
                'color' => '#d97706',
                'permissions' => [Permission::FinancialsView->value, Permission::TenantsView->value],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'finance-staff')->first();

        expect($role)->not->toBeNull();
        expect($role->label)->toBe('Finance Staff');
        expect($role->description)->toBe('Handles financials');
        expect($role->color)->toBe('#d97706');
        expect($role->is_system)->toBeFalse();
        expect($role->is_active)->toBeTrue();
        expect($role->hasPermissionTo(Permission::FinancialsView->value))->toBeTrue();
        expect($role->hasPermissionTo(Permission::TenantsView->value))->toBeTrue();
    });

    it('owner cannot assign unknown permission', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'test-role',
                'label' => 'Test Role',
                'permissions' => ['non-existent.permission'],
            ])
            ->assertSessionHasErrors('permissions.0');
    });

    it('owner can update custom role permissions', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'front-desk',
                'label' => 'Front Desk',
                'permissions' => [Permission::TenantsView->value],
            ]);

        $role = Role::where('name', 'front-desk')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->put(route('roles.update', $role), [
                'label' => 'Front Desk Updated',
                'description' => 'Manages front desk',
                'color' => '#0891b2',
                'permissions' => [Permission::TenantsView->value, Permission::TenantsUpdate->value],
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();

        expect($role->label)->toBe('Front Desk Updated');
        expect($role->description)->toBe('Manages front desk');
        expect($role->color)->toBe('#0891b2');
        expect($role->hasPermissionTo(Permission::TenantsUpdate->value))->toBeTrue();
        expect($role->hasPermissionTo(Permission::TenantsView->value))->toBeTrue();
    });

    it('owner can clone custom role', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'maintenance-staff',
                'label' => 'Maintenance Staff',
                'permissions' => [Permission::UnitsView->value],
            ]);

        $role = Role::where('name', 'maintenance-staff')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.clone', $role), [
                'name' => 'maintenance-staff-clone',
            ])
            ->assertRedirect(route('roles.index'));

        $clone = Role::where('name', 'maintenance-staff-clone')->first();

        expect($clone)->not->toBeNull();
        expect($clone->is_system)->toBeFalse();
        expect($clone->hasPermissionTo(Permission::UnitsView->value))->toBeTrue();
    });

    it('owner can delete custom role without affecting users', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'temp-role',
                'label' => 'Temp Role',
                'permissions' => [Permission::DashboardView->value],
            ]);

        $role = Role::where('name', 'temp-role')->first();
        $admin = User::factory()->admin()->create();
        $admin->assignRole($role);

        expect($admin->hasRole('temp-role'))->toBeTrue();

        $this->from(route('roles.index'))->actingAs($owner)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'temp-role')->exists())->toBeFalse();
        expect($admin->refresh()->exists())->toBeTrue();
        expect($admin->hasRole('temp-role'))->toBeFalse();
    });

    it('owner role cannot be deleted', function () {
        $owner = User::factory()->owner()->create();
        $ownerRole = Role::where('name', 'owner')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->delete(route('roles.destroy', $ownerRole))
            ->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'owner')->exists())->toBeTrue();
    });

    it('system role cannot be deleted', function () {
        $owner = User::factory()->owner()->create();
        $ownerRole = Role::where('name', 'owner')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->delete(route('roles.destroy', $ownerRole))
            ->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'owner')->exists())->toBeTrue();
    });

    it('owner can disable custom role', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'test-role',
                'label' => 'Test Role',
                'permissions' => [Permission::DashboardView->value],
            ]);

        $role = Role::where('name', 'test-role')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->put(route('roles.update', $role), [
                'is_active' => false,
                'permissions' => [Permission::DashboardView->value],
            ])
            ->assertRedirect(route('roles.index'));

        expect($role->refresh()->is_active)->toBeFalse();
    });

    it('owner can view create page with recommendations', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('roles/create')
                ->has('recommendations')
                ->has('permissionGroups')
            );
    });

    it('owner can view edit page', function () {
        $owner = User::factory()->owner()->create();

        $this->from(route('roles.index'))->actingAs($owner)
            ->post(route('roles.store'), [
                'name' => 'editable-role',
                'label' => 'Editable Role',
                'permissions' => [Permission::DashboardView->value],
            ]);

        $role = Role::where('name', 'editable-role')->first();

        $this->from(route('roles.index'))->actingAs($owner)
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('roles/edit')
                ->has('role')
                ->has('permissionGroups')
            );
    });
});
