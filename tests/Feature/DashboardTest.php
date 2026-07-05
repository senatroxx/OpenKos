<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createPropertyWithUnits(array $unitConfigs): Property
{
    $property = Property::factory()->create();

    foreach ($unitConfigs as $config) {
        $factory = Unit::factory();

        $state = $config['state'] ?? 'available';
        if ($state !== 'available') {
            $factory = $factory->{$state}();
        }

        $factory->create(['property_id' => $property->id]);
    }

    return $property;
}

test('dashboard returns occupancy stats matching seeded data', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    // 6 units total: 3 occupied, 2 available, 1 maintenance
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 6)
            ->where('stats.occupied_units', 3)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 1)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 50)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.name', $property->name)
            ->where('stats.properties.0.total_units', 6)
            ->where('stats.properties.0.occupied_units', 3)
            ->where('stats.properties.0.available_units', 2)
            ->where('stats.properties.0.maintenance_units', 1)
            ->where('stats.properties.0.unavailable_units', 0)
            ->where('stats.properties.0.occupancy_percentage', 50)
        );
});

test('dashboard counts occupied units from unit status, not lease presence', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
    ]);

    // Unit marked occupied even without a lease — still counted as occupied
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 3)
            ->where('stats.occupied_units', 1)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 33)
            ->where('stats.properties.0.occupied_units', 1)
            ->where('stats.properties.0.available_units', 2)
        );
});

test('dashboard returns correct stats for multiple properties', function () {
    $user = User::factory()->owner()->create();

    createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'maintenance'],
    ]);

    createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'occupied'],
        ['state' => 'available'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 6)
            ->where('stats.occupied_units', 3)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 1)
            ->where('stats.unavailable_units', 0)
            ->has('stats.properties', 2)
        );
});

test('dashboard returns zero stats when no properties exist', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 0)
            ->where('stats.occupied_units', 0)
            ->where('stats.available_units', 0)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 0)
            ->where('stats.occupancy_percentage', 0)
            ->where('stats.properties', [])
        );
});

test('dashboard does not count unavailable units as available', function () {
    $user = User::factory()->owner()->create();

    $property = createPropertyWithUnits([
        ['state' => 'occupied'],
        ['state' => 'available'],
        ['state' => 'available'],
        ['state' => 'unavailable'],
        ['state' => 'unavailable'],
    ]);

    // 5 units: 1 occupied, 2 available, 2 unavailable
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_units', 5)
            ->where('stats.occupied_units', 1)
            ->where('stats.available_units', 2)
            ->where('stats.maintenance_units', 0)
            ->where('stats.unavailable_units', 2)
            ->where('stats.occupancy_percentage', 20)
            ->has('stats.properties', 1)
            ->where('stats.properties.0.total_units', 5)
            ->where('stats.properties.0.occupied_units', 1)
            ->where('stats.properties.0.available_units', 2)
            ->where('stats.properties.0.maintenance_units', 0)
            ->where('stats.properties.0.unavailable_units', 2)
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
