<?php

use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
    $this->owner = User::factory()->owner()->create();
});

describe('tenant workspace', function () {
    it('renders each tab route with its component', function (string $route, string $component) {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->owner)
            ->get(route($route, $tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component($component)
                ->where('tenant.id', $tenant->id));
    })->with([
        ['tenants.show', 'tenants/show'],
        ['tenants.workspace.leases', 'tenants/leases'],
        ['tenants.workspace.documents', 'tenants/documents'],
    ]);
});

describe('lease workspace', function () {
    it('renders each tab route with its component', function (string $route, string $component) {
        $lease = Lease::factory()->create();

        $this->actingAs($this->owner)
            ->get(route($route, $lease))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component($component)
                ->where('lease.id', $lease->id));
    })->with([
        ['leases.show', 'leases/show'],
        ['leases.workspace.payments', 'leases/payments'],
        ['leases.workspace.documents', 'leases/documents'],
    ]);
});

describe('unit workspace', function () {
    it('renders the lease history tab', function () {
        $unit = Unit::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('properties.units.lease-history', [$unit->property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/lease-history')
                ->where('unit.id', $unit->id)
                ->has('leases.data')
                ->has('table.filters'));
    });
});

describe('maintenance ticket workspace', function () {
    it('renders the ticket workspace', function () {
        $ticket = MaintenanceTicket::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('maintenance-tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('maintenance-tickets/show')
                ->where('ticket.id', $ticket->id));
    });

    it('denies users without maintenance-tickets.view', function () {
        $user = User::factory()->create();
        $ticket = MaintenanceTicket::factory()->create();

        $this->actingAs($user)
            ->get(route('maintenance-tickets.show', $ticket))
            ->assertForbidden();
    });
});

describe('user workspace', function () {
    it('renders the user workspace', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($this->owner)
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.id', $user->id)
                ->has('user.roles')
                ->has('user.properties'));
    });
});

describe('role workspace', function () {
    it('renders the role workspace for owners', function () {
        $role = Role::whereName('owner')->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('roles.show', $role))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('roles/show')
                ->where('role.id', $role->id)
                ->has('role.permissions'));
    });

    it('denies non-owners the role workspace', function () {
        $admin = User::factory()->admin()->create();
        $role = Role::whereName('owner')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('roles.show', $role))
            ->assertForbidden();
    });
});
