<?php

use App\Models\Amenity;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows global and property-owned amenities in separate relationships', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $global = Amenity::factory()->create(['name' => 'WiFi']);
    $custom = Amenity::factory()->customFor($property)->create(['name' => 'Rooftop garden']);

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$global->id, $custom->id],
        ])
        ->assertRedirect();

    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $unitType->amenities()->sync([$global->id, $custom->id]);

    $otherProperty->facilities()->sync([$global->id]);

    expect($property->fresh()->facilities->modelKeys())->toEqualCanonicalizing([$global->id, $custom->id])
        ->and($otherProperty->fresh()->facilities->modelKeys())->toBe([$global->id])
        ->and($unitType->fresh()->amenities->modelKeys())->toEqualCanonicalizing([$global->id, $custom->id]);
});

it('rejects another property custom amenity', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $foreignAmenity = Amenity::factory()->customFor($otherProperty)->create();

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$foreignAmenity->id],
        ])
        ->assertSessionHasErrors('amenity_ids.0');

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), [
            'name' => 'Studio',
            'amenity_ids' => [$foreignAmenity->id],
        ])
        ->assertSessionHasErrors('amenity_ids.0');
});

it('scopes custom amenity names case-insensitively within a property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();

    Amenity::factory()->customFor($property)->create(['name' => 'Pool']);

    $this->actingAs($user)
        ->post(route('properties.amenities.store', $property), ['name' => 'pool'])
        ->assertSessionHasErrors('name');
});

it('enforces global amenity name uniqueness at the database boundary', function () {
    Amenity::factory()->create(['name' => 'WiFi']);

    expect(fn () => Amenity::create(['name' => 'wifi']))
        ->toThrow(QueryException::class);
});

it('enforces custom amenity name uniqueness within a property at the database boundary', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    Amenity::factory()->customFor($property)->create(['name' => 'Pool']);

    expect(fn () => Amenity::factory()->customFor($property)->create(['name' => 'pool']))
        ->toThrow(QueryException::class);

    Amenity::factory()->customFor($otherProperty)->create(['name' => 'pool']);

    expect(Amenity::query()->where('name', 'pool')->count())->toBe(1);
});

it('rejects cross-property custom amenities through direct pivot operations', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $amenity = Amenity::factory()->customFor($property)->create();
    $unitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);

    expect(fn () => $otherProperty->facilities()->attach($amenity))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $unitType->amenities()->sync([$amenity->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('allows global amenities through direct pivot operations across properties', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $amenity = Amenity::factory()->create();
    $unitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);

    $property->facilities()->attach($amenity);
    $unitType->amenities()->attach($amenity);

    expect($property->fresh()->facilities->modelKeys())->toBe([$amenity->id])
        ->and($unitType->fresh()->amenities->modelKeys())->toBe([$amenity->id]);
});

it('creates custom amenities for the current property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('properties.amenities.store', $property), ['name' => 'Bike storage'])
        ->assertRedirect();

    expect(Amenity::query()->sole()->owner_property_id)->toBe($property->id);
});

it('deactivates custom amenities without detaching existing associations', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $amenity = Amenity::factory()->customFor($property)->create();
    $inactiveAmenity = Amenity::factory()->customFor($property)->create(['is_active' => false]);
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $property->facilities()->attach($amenity);
    $unitType->amenities()->attach($amenity);

    $this->actingAs($user)
        ->post(route('properties.amenities.deactivate', [$property, $amenity]))
        ->assertRedirect();

    expect($amenity->refresh()->is_active)->toBeFalse()
        ->and($property->fresh()->facilities->modelKeys())->toBe([$amenity->id])
        ->and($unitType->fresh()->amenities->modelKeys())->toBe([$amenity->id]);

    $this->actingAs($user)
        ->put(route('properties.facilities.update', $property), [
            'amenity_ids' => [$amenity->id, $inactiveAmenity->id],
        ])
        ->assertSessionHasErrors('amenity_ids');

    expect($property->fresh()->facilities->modelKeys())->toBe([$amenity->id]);

    $this->actingAs($user)
        ->post(route('properties.amenities.restore', [$property, $amenity]))
        ->assertRedirect();

    expect($amenity->refresh()->is_active)->toBeTrue();
});
