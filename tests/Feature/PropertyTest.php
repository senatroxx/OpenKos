<?php

use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $this->get(route('properties.index'))->assertRedirect('login');
    });

    it('returns 403 for users without properties.view permission', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });

    it('allows admin to access properties', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('allows owner to access properties', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('denies staff access to properties', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists properties on the index page', function () {
        $user = User::factory()->owner()->create();
        Property::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/index')
                ->has('properties.data', 3)
            );
    });

    it('creates a property', function () {
        $user = User::factory()->owner()->create();
        $region = Region::where('name', 'DKI Jakarta')->first();
        $city = City::where('name', 'Kota Jakarta Selatan')->first();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Kos Melati',
            'region_id' => $region->id,
            'city_id' => $city->id,
        ]);

        $property = Property::with('region', 'city')->first();

        expect($property)->not->toBeNull();
        expect($property->name)->toBe('Kos Melati');
        expect($property->region->name)->toBe('DKI Jakarta');
        expect($property->city->name)->toBe('Kota Jakarta Selatan');
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [])
            ->assertSessionHasErrors('name');
    });

    it('updates a property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['name' => 'Kos Melati']);

        $this->actingAs($user)
            ->put(route('properties.update', $property), [
                'name' => 'Kos Mawar',
            ]);

        $property->refresh();

        expect($property->name)->toBe('Kos Mawar');
    });

    it('archives a property by setting is_active to false', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)->delete(route('properties.destroy', $property));

        $property->refresh();

        expect($property->is_active)->toBeFalse();
    });
});

describe('cross-property access', function () {
    it('denies admin from updating a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->put(route('properties.update', $propertyB), ['name' => 'Hacked Name'])
            ->assertForbidden();
    });

    it('denies admin from archiving a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->delete(route('properties.destroy', $propertyB))
            ->assertForbidden();
    });
});
