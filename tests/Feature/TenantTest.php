<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $this->get(route('tenants.index'))->assertRedirect('login');
    });

    it('returns 403 for users without tenants.view permission', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertForbidden();
    });

    it('allows admin to access tenants', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk();
    });

    it('allows owner to access tenants', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk();
    });

    it('allows staff to access tenants', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk();
    });
});

describe('CRUD', function () {
    it('lists tenants on the index page', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tenants/index')
                ->has('tenants.data', 3)
            );
    });

    it('creates a tenant', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->post(route('tenants.store'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'id_card_number' => '3273010203040005',
        ]);

        $tenant = Tenant::first();

        expect($tenant)->not->toBeNull();
        expect($tenant->name)->toBe('Budi Santoso');
        expect($tenant->phone)->toBe('081234567890');
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('tenants.store'), [])
            ->assertSessionHasErrors('name');
    });

    it('updates a tenant', function () {
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create(['name' => 'Budi Santoso']);

        $this->actingAs($user)
            ->put(route('tenants.update', $tenant), [
                'name' => 'Budi Santoso Updated',
                'is_active' => false,
            ]);

        $tenant->refresh();

        expect($tenant->name)->toBe('Budi Santoso Updated');
        expect($tenant->is_active)->toBeFalse();
    });

    it('archives a tenant via soft delete', function () {
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->delete(route('tenants.destroy', $tenant));

        expect(Tenant::count())->toBe(0);
        expect(Tenant::withTrashed()->count())->toBe(1);
    });

    it('searches tenants by name', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create(['name' => 'Budi Santoso']);
        Tenant::factory()->create(['name' => 'Siti Nurhaliza']);

        $response = $this->actingAs($user)
            ->get(route('tenants.index', ['search' => 'Budi']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('tenants/index')
            ->has('tenants.data', 1)
        );
    });

    it('searches tenants by phone', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create(['name' => 'Budi', 'phone' => '081234567890']);
        Tenant::factory()->create(['name' => 'Siti', 'phone' => '081234567891']);

        $response = $this->actingAs($user)
            ->get(route('tenants.index', ['search' => '890']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('tenants/index')
            ->has('tenants.data', 1)
        );
    });

    it('filters active tenants', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create(['is_active' => true]);
        Tenant::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)
            ->get(route('tenants.index', ['status' => 'active']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('tenants/index')
            ->has('tenants.data', 1)
        );
    });

    it('filters inactive tenants', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create(['is_active' => true]);
        Tenant::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)
            ->get(route('tenants.index', ['status' => 'inactive']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('tenants/index')
            ->has('tenants.data', 1)
        );
    });

    it('filters archived tenants', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create();
        $archived = Tenant::factory()->create();
        $archived->delete();

        $response = $this->actingAs($user)
            ->get(route('tenants.index', ['status' => 'archived']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('tenants/index')
            ->has('tenants.data', 1)
        );
    });
});
