<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createPropertyWithRoom(): array
{
    $property = Property::factory()->create();
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'base_price' => 1_000_000,
    ]);

    return [$property, $room];
}

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        [$property, $room] = createPropertyWithRoom();

        $this->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertRedirect('login');
    });

    it('returns 403 for users without properties.manage permission', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertForbidden();
    });

    it('allows admin to access leases', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->admin()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertOk();
    });

    it('allows owner to access leases', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertOk();
    });

    it('denies staff access to leases', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists lease history for a room', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'status' => 'active',
        ]);

        Lease::factory()->terminated()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.rooms.leases.index', [$property, $room]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/rooms/leases/index')
                ->has('leases', 2)
            );
    });

    it('creates a lease and assigns tenant to room', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.rooms.leases.store', [$property, $room]), [
            'tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
            'monthly_rent' => 1_500_000,
            'deposit_amount' => 1_000_000,
            'rent_due_day' => 5,
        ]);

        $lease = Lease::first();

        expect($lease)->not->toBeNull();
        expect($lease->tenant_id)->toBe($tenant->id);
        expect($lease->room_id)->toBe($room->id);
        expect($lease->monthly_rent)->toBe('1500000.00');
        expect($lease->deposit_amount)->toBe('1000000.00');
        expect($lease->rent_due_day)->toBe(5);
        expect($lease->status)->toBe('active');
    });

    it('uses room base price when monthly rent is not specified', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.rooms.leases.store', [$property, $room]), [
            'tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
        ]);

        $lease = Lease::first();

        expect($lease->monthly_rent)->toBe($room->base_price);
    });

    it('validates required fields on create', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.rooms.leases.store', [$property, $room]), [])
            ->assertSessionHasErrors(['tenant_id', 'start_date']);
    });

    it('prevents assigning tenant to an already occupied room', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Lease::factory()->create([
            'tenant_id' => $tenantA->id,
            'room_id' => $room->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.rooms.leases.store', [$property, $room]), [
                'tenant_id' => $tenantB->id,
                'start_date' => '2026-06-01',
            ])
            ->assertStatus(422);
    });

    it('updates a lease', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'monthly_rent' => 1_000_000,
        ]);

        $this->actingAs($user)
            ->put(route('properties.rooms.leases.update', [$property, $room, $lease]), [
                'monthly_rent' => 2_000_000,
            ]);

        $lease->refresh();

        expect($lease->monthly_rent)->toBe('2000000.00');
    });
});

describe('termination', function () {
    it('terminates an active lease', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'status' => 'active',
            'end_date' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('properties.rooms.leases.destroy', [$property, $room, $lease]));

        $lease->refresh();

        expect($lease->status)->toBe('terminated');
        expect($lease->end_date)->not->toBeNull();
        expect($lease->termination_date)->not->toBeNull();
    });
});

describe('move room', function () {
    it('moves a tenant to a new room', function () {
        [$property, $roomA] = createPropertyWithRoom();
        $roomB = Room::factory()->create([
            'property_id' => $property->id,
            'base_price' => 1_200_000,
        ]);

        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $roomA->id,
            'status' => 'active',
            'monthly_rent' => 1_000_000,
            'deposit_amount' => 500_000,
            'deposit_paid_at' => now(),
            'rent_due_day' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('properties.rooms.leases.move', [$property, $roomA, $lease]), [
                'target_room_id' => $roomB->id,
            ]);

        $lease->refresh();

        expect($lease->status)->toBe('terminated');
        expect($lease->end_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));

        $newLease = Lease::where('room_id', $roomB->id)->first();

        expect($newLease)->not->toBeNull();
        expect($newLease->tenant_id)->toBe($tenant->id);
        expect($newLease->status)->toBe('active');
        expect($newLease->monthly_rent)->toBe('1000000.00');
        expect($newLease->deposit_amount)->toBe('500000.00');
    });

    it('prevents moving to an already occupied room', function () {
        [$property, $roomA] = createPropertyWithRoom();
        $roomB = Room::factory()->create([
            'property_id' => $property->id,
        ]);

        $user = User::factory()->owner()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $leaseA = Lease::factory()->create([
            'tenant_id' => $tenantA->id,
            'room_id' => $roomA->id,
            'status' => 'active',
        ]);

        Lease::factory()->create([
            'tenant_id' => $tenantB->id,
            'room_id' => $roomB->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('properties.rooms.leases.move', [$property, $roomA, $leaseA]), [
                'target_room_id' => $roomB->id,
            ])
            ->assertStatus(422);
    });
});

describe('room occupancy derived from lease', function () {
    it('shows room as occupied after lease creation', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)->post(route('properties.rooms.leases.store', [$property, $room]), [
            'tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
        ]);

        $room->refresh();

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/rooms/index')
                ->has('rooms.data', 1)
                ->where('rooms.data.0.active_leases', 1)
            );
    });

    it('shows room as available after lease termination', function () {
        [$property, $room] = createPropertyWithRoom();
        $user = User::factory()->owner()->create();
        $tenant = Tenant::factory()->create();

        $lease = Lease::factory()->create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'status' => 'active',
            'end_date' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('properties.rooms.leases.destroy', [$property, $room, $lease]));

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/rooms/index')
                ->has('rooms.data', 1)
                ->where('rooms.data.0.active_leases', 0)
            );
    });
});
