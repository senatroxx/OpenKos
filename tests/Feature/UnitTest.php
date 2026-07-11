<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $property = Property::factory()->create();

        $this->get(route('properties.units.index', $property))->assertRedirect('login');
    });

    it('returns 403 for users without units.view permission', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]))
            ->assertForbidden();
    });
});

describe('archive lifecycle', function () {
    it('blocks deleting a unit with active leases', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        Lease::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]))
            ->assertRedirect();
    });
});

describe('authorization', function () {
    it('allows admin to access units', function () {
        $user = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk();
    });

    it('allows owner to access units', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk();
    });
});

describe('CRUD', function () {
    it('lists units on the index page', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->count(3)->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/index')
                ->has('units.data', 3)
            );
    });

    it('creates a unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($user)->post(route('properties.units.store', $property), [
            'name' => 'Unit 101',
            'capacity' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit = Unit::first();

        expect($unit)->not->toBeNull();
        expect($unit->name)->toBe('Unit 101');
        expect($unit->property_id)->toBe($property->id);
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.store', $property), [])
            ->assertSessionHasErrors(['name', 'capacity']);
    });

    it('updates a unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create(['name' => 'Unit 101']);

        $this->actingAs($user)
            ->put(route('properties.units.update', [$property, $unit]), [
                'name' => 'Unit 102',
                'capacity' => 2,
            ]);

        $unit->refresh();

        expect($unit->name)->toBe('Unit 102');
    });

    it('deletes a unit via soft delete', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]));

        expect(Unit::count())->toBe(0);
        expect(Unit::withTrashed()->count())->toBe(1);
    });

    it('filters units by status', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->for($property)->count(2)->create();
        Unit::factory()->for($property)->occupied()->create();
        Unit::factory()->for($property)->maintenance()->create();

        $response = $this->actingAs($user)
            ->get(route('properties.units.index', [$property, 'status' => 'occupied']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->has('units.data', 1)
            ->where('status', 'occupied')
        );
    });

    it('searches units by name', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->for($property)->create(['name' => 'Deluxe Suite']);
        Unit::factory()->for($property)->create(['name' => 'Standard Unit']);

        $response = $this->actingAs($user)
            ->get(route('properties.units.index', [$property, 'search' => 'Deluxe']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->has('units.data', 1)
        );
    });
});

describe('cross-property access', function () {
    it('denies admin viewing units of a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->get(route('properties.units.index', $propertyB))
            ->assertForbidden();
    });

    it('denies admin creating a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->post(route('properties.units.store', $propertyB), [
                'name' => 'Unit 101',
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin updating a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->put(route('properties.units.update', [$propertyB, $unit]), [
                'name' => 'Hacked',
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin deleting a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->delete(route('properties.units.destroy', [$propertyB, $unit]))
            ->assertForbidden();
    });
});

describe('slug', function () {
    it('auto-generates a slug from the name', function () {
        $unit = Unit::factory()->create(['name' => 'Unit A1']);

        expect($unit->slug)->toBe('unit-a1');
    });

    it('allows the same slug across different properties', function () {
        $a = Unit::factory()->for(Property::factory())->create(['name' => 'A1']);
        $b = Unit::factory()->for(Property::factory())->create(['name' => 'A1']);

        expect($a->slug)->toBe('a1')->and($b->slug)->toBe('a1');
    });

    it('disambiguates a slug collision within a property', function () {
        $property = Property::factory()->create();

        // Distinct names (property_id + name is unique) that slugify identically.
        $first = Unit::factory()->for($property)->create(['name' => 'A1']);
        $second = Unit::factory()->for($property)->create(['name' => 'A1.']);

        expect($first->slug)->toBe('a1')->and($second->slug)->toBe('a1-2');
    });
});
