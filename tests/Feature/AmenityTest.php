<?php

use App\Enums\AmenityScope;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows one shared amenity across properties and Unit Types', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $unitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);
    $amenity = Amenity::factory()->create([
        'name' => 'Wi-Fi',
        'scope' => AmenityScope::Both,
    ]);

    $property->facilities()->attach($amenity);
    $otherProperty->facilities()->attach($amenity);
    $unitType->amenities()->attach($amenity);

    expect($property->fresh()->facilities->modelKeys())->toBe([$amenity->id])
        ->and($otherProperty->fresh()->facilities->modelKeys())->toBe([$amenity->id])
        ->and($unitType->fresh()->amenities->modelKeys())->toBe([$amenity->id]);
});

it('offers amenities only in their declared workspace scope', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    UnitType::factory()->for($property)->create(['name' => 'Studio']);
    Amenity::factory()->create([
        'name' => 'Parking',
        'scope' => AmenityScope::Property,
    ]);
    Amenity::factory()->create([
        'name' => 'Air conditioning',
        'scope' => AmenityScope::UnitType,
    ]);
    Amenity::factory()->create([
        'name' => 'Laundry',
        'scope' => AmenityScope::Both,
    ]);

    $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertInertia(fn ($page) => $page
            ->where('amenities', fn ($amenities): bool => $amenities->pluck('name')->sort()->values()->all() === ['Laundry', 'Parking']));

    $this->actingAs($user)
        ->get(route('properties.unit-types.index', $property))
        ->assertInertia(fn ($page) => $page
            ->where('amenities', fn ($amenities): bool => $amenities->pluck('name')->sort()->values()->all() === ['Air conditioning', 'Laundry']));
});

it('rejects incompatible scope assignments through direct pivots', function () {
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $propertyOnly = Amenity::factory()->create([
        'name' => 'Parking',
        'scope' => AmenityScope::Property,
    ]);
    $unitTypeOnly = Amenity::factory()->create([
        'name' => 'TV',
        'scope' => AmenityScope::UnitType,
    ]);

    expect(fn () => $unitType->amenities()->attach($propertyOnly))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $property->facilities()->attach($unitTypeOnly))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects incompatible scope assignments through workspace requests', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $propertyOnly = Amenity::factory()->create(['scope' => AmenityScope::Property]);
    $unitTypeOnly = Amenity::factory()->create(['scope' => AmenityScope::UnitType]);

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$unitTypeOnly->id],
        ])
        ->assertSessionHasErrors('amenity_ids.0');

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), [
            'name' => 'Studio',
            'amenity_ids' => [$propertyOnly->id],
        ])
        ->assertSessionHasErrors('amenity_ids.0');
});

it('does not allow inactive amenities to be newly assigned', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $amenity = Amenity::factory()->create([
        'scope' => AmenityScope::Both,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$amenity->id],
        ])
        ->assertSessionHasErrors('amenity_ids');

    expect($property->fresh()->facilities)->toHaveCount(0);
});

it('preserves inactive assignments and allows them to be removed', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $amenity = Amenity::factory()->create(['scope' => AmenityScope::Property]);

    $property->facilities()->attach($amenity);
    $amenity->update(['is_active' => false]);

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$amenity->id],
        ])
        ->assertRedirect();

    expect($property->fresh()->facilities->modelKeys())->toBe([$amenity->id])
        ->and($property->fresh()->facilities->first()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [],
        ])
        ->assertRedirect();

    expect($property->fresh()->facilities)->toHaveCount(0);
});

it('enforces trimmed case-insensitive global name uniqueness at the database boundary', function () {
    Amenity::factory()->create(['name' => ' Wi-Fi ']);

    expect(fn () => Amenity::factory()->create(['name' => 'wi-fi']))
        ->toThrow(QueryException::class);
});
