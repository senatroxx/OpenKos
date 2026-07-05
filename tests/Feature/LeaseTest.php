<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createPropertyWithUnit(): array
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1_000_000)->create([
        'property_id' => $property->id,
    ]);

    return [$property, $unit];
}

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        [$property, $unit] = createPropertyWithUnit();

        $this->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertRedirect('login');
    });

    it('returns 403 for users without leases.view permission', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertForbidden();
    });

    it('allows admin to access leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->admin()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk();
    });

    it('allows owner to access leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk();
    });

    it('denies staff access to leases', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists lease history for a unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        Lease::factory()->terminated()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.leases.index', [$property, $unit]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/leases/index')
                ->has('leases', 2)
            );
    });

    it('creates a lease and assigns tenant to unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
            'rent_amount' => 1_500_000,
            'billing_unit' => 'month',
            'billing_interval' => 1,
            'deposit_amount' => 1_000_000,
            'rent_due_day' => 5,
        ]);

        $lease = Lease::first();

        expect($lease)->not->toBeNull();
        expect($lease->primary_tenant_id)->toBe($tenant->id);
        expect($lease->unit_id)->toBe($unit->id);
        expect($lease->rent_amount)->toBe('1500000.00');
        expect($lease->deposit_amount)->toBe('1000000.00');
        expect($lease->rent_due_day)->toBe(5);
        expect($lease->status)->toBe('active');
    });

    it('uses unit base price when rent amount is not specified', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
        ]);

        $lease = Lease::first();

        expect($lease->rent_amount)->toBe($unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount'));
    });

    it('validates required fields on create', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [])
            ->assertSessionHasErrors(['tenant_ids', 'start_date']);
    });

    it('prevents assigning tenant to an already occupied unit', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Lease::factory()->create([
            'primary_tenant_id' => $tenantA->id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.store', [$property, $unit]), [
                'tenant_ids' => [$tenantB->id],
                'start_date' => '2026-06-01',
            ])
            ->assertStatus(422);
    });

    it('updates a lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'rent_amount' => 1_000_000,
        ]);

        $this->actingAs($user)
            ->put(route('properties.units.leases.update', [$property, $unit, $lease]), [
                'rent_amount' => 2_000_000,
            ]);

        $lease->refresh();

        expect($lease->rent_amount)->toBe('2000000.00');
    });
});

describe('termination', function () {
    it('terminates an active lease', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'end_date' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('properties.units.leases.destroy', [$property, $unit, $lease]));

        $lease->refresh();

        expect($lease->status)->toBe('terminated');
        expect($lease->end_date)->not->toBeNull();
        expect($lease->termination_date)->not->toBeNull();
    });
});

describe('move unit', function () {
    it('moves a tenant to a new unit', function () {
        [$property, $unitA] = createPropertyWithUnit();
        $unitB = Unit::factory()->withRate(1_200_000)->create([
            'property_id' => $property->id,
        ]);

        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
            'rent_amount' => 1_000_000,
            'deposit_amount' => 500_000,
            'deposit_paid_at' => now(),
            'rent_due_day' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.move', [$property, $unitA, $lease]), [
                'target_unit_id' => $unitB->id,
            ]);

        $lease->refresh();

        expect($lease->status)->toBe('terminated');
        expect($lease->end_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));

        $newLease = Lease::where('unit_id', $unitB->id)->first();

        expect($newLease)->not->toBeNull();
        expect($newLease->primary_tenant_id)->toBe($tenant->id);
        expect($newLease->status)->toBe('active');
        expect($newLease->rent_amount)->toBe('1000000.00');
        expect($newLease->deposit_amount)->toBe('500000.00');
    });

    it('denies admin from accessing leases of a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->get(route('properties.units.leases.index', [$propertyB, $unitB]))
            ->assertForbidden();
    });

    it('denies admin from creating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->post(route('properties.units.leases.store', [$propertyB, $unitB]), [
                'tenant_ids' => [$tenant->id],
                'start_date' => '2026-06-01',
            ])
            ->assertForbidden();
    });

    it('denies admin from updating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
        ]);

        $this->actingAs($admin)
            ->put(route('properties.units.leases.update', [$propertyB, $unitB, $lease]), [
                'rent_amount' => 9_999_999,
            ])
            ->assertForbidden();
    });

    it('denies admin from terminating a lease in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('properties.units.leases.destroy', [$propertyB, $unitB, $lease]))
            ->assertForbidden();
    });

    it('denies admin from moving a lease to a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA] = createPropertyWithUnit();
        [$propertyB, $unitB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('properties.units.leases.move', [$propertyB, $unitB, $lease]), [
                'target_unit_id' => Unit::factory()->for($propertyB)->create()->id,
            ])
            ->assertForbidden();
    });

    it('denies admin from moving a lease to a target unit in a different property', function () {
        $admin = User::factory()->admin()->create();
        [$propertyA, $unitA] = createPropertyWithUnit();
        [$propertyB] = createPropertyWithUnit();
        $admin->properties()->sync([$propertyA->id]);
        $tenant = Tenant::factory()->create();
        $lease = Lease::factory()->create([
            'primary_tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
        ]);
        $targetUnitInB = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->post(route('properties.units.leases.move', [$propertyA, $unitA, $lease]), [
                'target_unit_id' => $targetUnitInB->id,
            ])
            ->assertForbidden();
    });

    it('prevents moving to an already occupied unit', function () {
        [$property, $unitA] = createPropertyWithUnit();
        $unitB = Unit::factory()->create([
            'property_id' => $property->id,
        ]);

        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $leaseA = Lease::factory()->create([
            'primary_tenant_id' => $tenantA->id,
            'unit_id' => $unitA->id,
            'status' => 'active',
        ]);

        Lease::factory()->create([
            'primary_tenant_id' => $tenantB->id,
            'unit_id' => $unitB->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.units.leases.move', [$property, $unitA, $leaseA]), [
                'target_unit_id' => $unitB->id,
            ])
            ->assertStatus(422);
    });
});

describe('unit occupancy derived from lease', function () {
    it('shows unit as occupied after lease creation', function () {
        [$property, $unit] = createPropertyWithUnit();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.units.leases.store', [$property, $unit]), [
            'tenant_ids' => [$tenant->id],
            'start_date' => '2026-06-01',
        ]);

        $unit->refresh();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/index')
                ->has('units.data', 1)
                ->where('units.data.0.active_leases', 1)
            );
    });
});
