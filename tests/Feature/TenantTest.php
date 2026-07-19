<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
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
            'phone' => '+6281234567890',
            'id_card_number' => '3273010203040005',
        ]);

        $tenant = Tenant::first();

        expect($tenant)->not->toBeNull();
        expect($tenant->name)->toBe('Budi Santoso');
        expect($tenant->phone)->toBe('+6281234567890');
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
        // Pin id_card_number: search also matches it, and the factory's random
        // 16-digit default could otherwise coincidentally contain '890'.
        Tenant::factory()->create(['name' => 'Budi', 'phone' => '081234567890', 'id_card_number' => '1111111111111111']);
        Tenant::factory()->create(['name' => 'Siti', 'phone' => '081234567891', 'id_card_number' => '2222222222222222']);

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

describe('cross-property access', function () {
    it('denies admin from updating a tenant in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $tenant = Tenant::factory()->create();
        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($admin)
            ->put(route('tenants.update', $tenant), ['name' => 'Hacked Name'])
            ->assertForbidden();
    });

    it('denies admin from restoring a tenant in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $tenant = Tenant::factory()->create();
        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);
        $tenant->delete();

        $this->actingAs($admin)
            ->post(route('tenants.restore', $tenant))
            ->assertForbidden();
    });

    it('denies admin from archiving a tenant in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $tenant = Tenant::factory()->create();
        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('tenants.destroy', $tenant))
            ->assertForbidden();
    });
});

describe('archive lifecycle', function () {
    it('restores an archived tenant', function () {
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        expect(Tenant::count())->toBe(0);

        $this->actingAs($user)
            ->post(route('tenants.restore', $tenant))
            ->assertRedirect();

        expect(Tenant::count())->toBe(1);
    });

    it('blocks archiving a tenant with active leases', function () {
        $owner = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();
        $unit = Unit::factory()->create();
        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($owner)
            ->from(route('tenants.index'))
            ->delete(route('tenants.destroy', $tenant))
            ->assertRedirect(route('tenants.index'));
    });
});

describe('unit assignment authorization', function () {
    it('denies admin assigning a tenant to a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $unitInB = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->post(route('tenants.assign-unit', $tenant), [
                'unit_id' => $unitInB->id,
                'start_date' => '2026-06-01',
            ])
            ->assertForbidden();
    });
});

describe('unit assignment pricing', function () {
    it('ships available units with their active rates for the picker', function () {
        $user = User::factory()->owner()->create();
        Tenant::factory()->create();
        Unit::factory()->create(); // auto-seeds a default monthly rate

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('availableUnits.0.active_rates.0.amount'));
    });

    it('derives rent from the selected unit rate', function () {
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();
        $unit = Unit::factory()->withRate(1_750_000)->create();
        $rate = $unit->rates()->first();

        $this->actingAs($user)->post(route('tenants.assign-unit', $tenant), [
            'unit_id' => $unit->id,
            'unit_rate_id' => $rate->id,
            'start_date' => '2026-06-01',
        ]);

        $lease = Lease::first();

        expect($lease->unit_rate_id)->toBe($rate->id)
            ->and((float) $lease->rent_amount)->toBe(1_750_000.0)
            ->and($lease->is_custom_price)->toBeFalse();
    });
});
