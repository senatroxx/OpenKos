<?php

use App\Models\Amenity;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

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
        ->put(route('properties.facilities.update', $property), ['amenity_ids' => [$amenity->id]])
        ->assertRedirect();

    expect($property->fresh()->facilities->modelKeys())->toBe([$amenity->id]);
});
