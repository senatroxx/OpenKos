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

function createPropertyWithRooms(array $roomConfigs): Property
{
    $property = Property::factory()->create();

    foreach ($roomConfigs as $config) {
        $factory = Room::factory();

        if ($config['state'] !== 'available') {
            $factory = $factory->{$config['state']}();
        }

        $room = $factory->create(['property_id' => $property->id]);

        if (($config['with_active_lease'] ?? false)) {
            Lease::factory()->create([
                'room_id' => $room->id,
                'tenant_id' => Tenant::factory()->create()->id,
            ]);
        }
    }

    return $property;
}

test('dashboard returns occupancy stats matching seeded data', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithRooms([
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    // 6 rooms total: 3 occupied, 2 available, 1 maintenance
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_rooms', 6)
            ->where('stats.occupied_rooms', 3)
            ->where('stats.available_rooms', 2)
            ->where('stats.maintenance_rooms', 1)
            ->where('stats.unavailable_rooms', 0)
            ->where('stats.occupancy_percentage', 50)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.name', $property->name)
            ->where('stats.properties.0.total_rooms', 6)
            ->where('stats.properties.0.occupied_rooms', 3)
            ->where('stats.properties.0.available_rooms', 2)
            ->where('stats.properties.0.maintenance_rooms', 1)
            ->where('stats.properties.0.unavailable_rooms', 0)
            ->where('stats.properties.0.occupancy_percentage', 50)
        );
});

test('dashboard returns correct stats for multiple properties', function () {
    $user = User::factory()->owner()->create();

    $propertyA = createPropertyWithRooms([
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    $propertyB = createPropertyWithRooms([
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'available'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_rooms', 6)
            ->where('stats.occupied_rooms', 3)
            ->where('stats.available_rooms', 2)
            ->where('stats.maintenance_rooms', 1)
            ->where('stats.unavailable_rooms', 0)
            ->has('stats.properties', 2)
        );
});

test('dashboard returns zero stats when no properties exist', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_rooms', 0)
            ->where('stats.occupied_rooms', 0)
            ->where('stats.available_rooms', 0)
            ->where('stats.maintenance_rooms', 0)
            ->where('stats.unavailable_rooms', 0)
            ->where('stats.occupancy_percentage', 0)
            ->where('stats.properties', [])
        );
});

test('dashboard does not count unavailable rooms as available', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithRooms([
        ['state' => 'occupied', 'with_active_lease' => true],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'unavailable'],
        ['state' => 'unavailable'],
    ]);

    // 5 rooms: 1 occupied, 2 available, 2 unavailable
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_rooms', 5)
            ->where('stats.occupied_rooms', 1)
            ->where('stats.available_rooms', 2)
            ->where('stats.maintenance_rooms', 0)
            ->where('stats.unavailable_rooms', 2)
            ->where('stats.occupancy_percentage', 20)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.total_rooms', 5)
            ->where('stats.properties.0.occupied_rooms', 1)
            ->where('stats.properties.0.available_rooms', 2)
            ->where('stats.properties.0.maintenance_rooms', 0)
            ->where('stats.properties.0.unavailable_rooms', 2)
            ->where('stats.properties.0.occupancy_percentage', 20)
        );
});

test('dashboard requires authentication', function () {
    $this->get(route('dashboard'))->assertRedirect('login');
});

test('dashboard requires dashboard.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
