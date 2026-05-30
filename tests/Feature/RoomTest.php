<?php

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $property = Property::factory()->create();

        $this->get(route('properties.rooms.index', $property))->assertRedirect('login');
    });

    it('returns 403 for users without rooms.view permission', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertForbidden();
    });

    it('allows admin to access rooms', function () {
        $user = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertOk();
    });

    it('allows owner to access rooms', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertOk();
    });
});

describe('CRUD', function () {
    it('lists rooms on the index page', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Room::factory()->count(3)->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.rooms.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/rooms/index')
                ->has('rooms.data', 3)
            );
    });

    it('creates a room', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)->post(route('properties.rooms.store', $property), [
            'name' => 'Room 101',
            'base_price' => 1_000_000,
            'capacity' => 1,
        ]);

        $room = Room::first();

        expect($room)->not->toBeNull();
        expect($room->name)->toBe('Room 101');
        expect($room->property_id)->toBe($property->id);
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.rooms.store', $property), [])
            ->assertSessionHasErrors(['name', 'base_price', 'capacity']);
    });

    it('updates a room', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $room = Room::factory()->for($property)->create(['name' => 'Room 101']);

        $this->actingAs($user)
            ->put(route('properties.rooms.update', [$property, $room]), [
                'name' => 'Room 102',
                'base_price' => 1_500_000,
                'capacity' => 2,
            ]);

        $room->refresh();

        expect($room->name)->toBe('Room 102');
        expect($room->base_price)->toBe('1500000.00');
    });

    it('deletes a room via soft delete', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $room = Room::factory()->for($property)->create();

        $this->actingAs($user)
            ->delete(route('properties.rooms.destroy', [$property, $room]));

        expect(Room::count())->toBe(0);
        expect(Room::withTrashed()->count())->toBe(1);
    });

    it('filters rooms by status', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Room::factory()->for($property)->count(2)->create();
        Room::factory()->for($property)->occupied()->create();
        Room::factory()->for($property)->maintenance()->create();

        $response = $this->actingAs($user)
            ->get(route('properties.rooms.index', [$property, 'status' => 'occupied']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/rooms/index')
            ->has('rooms.data', 1)
            ->where('status', 'occupied')
        );
    });

    it('searches rooms by name', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Room::factory()->for($property)->create(['name' => 'Deluxe Suite']);
        Room::factory()->for($property)->create(['name' => 'Standard Room']);

        $response = $this->actingAs($user)
            ->get(route('properties.rooms.index', [$property, 'search' => 'Deluxe']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/rooms/index')
            ->has('rooms.data', 1)
        );
    });
});

describe('cross-property access', function () {
    it('denies admin viewing rooms of a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->get(route('properties.rooms.index', $propertyB))
            ->assertForbidden();
    });

    it('denies admin creating a room in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->post(route('properties.rooms.store', $propertyB), [
                'name' => 'Room 101',
                'base_price' => 1_000_000,
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin updating a room in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $room = Room::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->put(route('properties.rooms.update', [$propertyB, $room]), [
                'name' => 'Hacked',
                'base_price' => 1_000_000,
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin deleting a room in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $room = Room::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->delete(route('properties.rooms.destroy', [$propertyB, $room]))
            ->assertForbidden();
    });
});
