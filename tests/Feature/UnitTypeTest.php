<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lists UnitTypes inside the property workspace', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    UnitType::factory()->for($property)->create(['name' => 'Studio']);

    $this->actingAs($user)
        ->get(route('properties.unit-types.index', $property))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('properties/unit-types/index')
            ->has('unitTypes', 1)
            ->where('unitTypes.0.name', 'Studio')
        );
});

it('creates a UnitType with nullable structured fields', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), ['name' => 'Flexible Type'])
        ->assertRedirect();

    $unitType = UnitType::query()->sole();

    expect($unitType->property_id)->toBe($property->id)
        ->and($unitType->bedrooms)->toBeNull()
        ->and($unitType->bathrooms)->toBeNull()
        ->and($unitType->size_sqm)->toBeNull()
        ->and($unitType->furnishing)->toBeNull();
});

it('scopes UnitType names to a property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();

    UnitType::factory()->for($property)->create(['name' => 'Studio']);

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), ['name' => 'Studio'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $otherProperty), ['name' => 'Studio'])
        ->assertRedirect();

    expect(UnitType::query()->where('name', 'Studio')->count())->toBe(2);
});

it('scopes UnitType names case-insensitively within a property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    UnitType::factory()->for($property)->create(['name' => 'Studio']);

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), ['name' => 'studio'])
        ->assertSessionHasErrors('name');
});

it('enforces UnitType name uniqueness at the database boundary', function () {
    $property = Property::factory()->create();
    UnitType::factory()->for($property)->create(['name' => 'Studio']);

    expect(fn () => UnitType::factory()->for($property)->create(['name' => 'studio']))
        ->toThrow(QueryException::class);
});

it('rejects assigning a UnitType from another property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $otherType = UnitType::factory()->for($otherProperty)->create();

    $this->actingAs($user)
        ->post(route('properties.units.store', $property), [
            'name' => 'Unit 101',
            'capacity' => 1,
            'unit_type_id' => $otherType->id,
        ])
        ->assertSessionHasErrors('unit_type_id');
});

it('enforces property consistency at the database boundary', function () {
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $otherType = UnitType::factory()->for($otherProperty)->create();
    $unit = Unit::factory()->for($property)->make([
        'name' => 'Unit 101',
        'unit_type_id' => $otherType->id,
    ]);

    expect(fn () => $unit->save())->toThrow(QueryException::class);
});

it('deactivates without detaching units and blocks new assignments', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->actingAs($user)
        ->post(route('properties.unit-types.deactivate', [$property, $unitType]))
        ->assertRedirect();

    expect($unitType->refresh()->is_active)->toBeFalse()
        ->and($unit->refresh()->unit_type_id)->toBe($unitType->id);

    $this->actingAs($user)
        ->post(route('properties.units.store', $property), [
            'name' => 'Unit 102',
            'capacity' => 1,
            'unit_type_id' => $unitType->id,
        ])
        ->assertSessionHasErrors('unit_type_id');
});

it('allows an existing Unit to be reassigned away from an inactive UnitType', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio', 'is_active' => false]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->actingAs($user)
        ->put(route('properties.units.update', [$property, $unit]), [
            'name' => $unit->name,
            'capacity' => $unit->capacity,
            'status' => $unit->status->value,
            'updated_at' => $unit->updated_at->toISOString(),
            'unit_type_id' => null,
        ])
        ->assertRedirect();

    expect($unit->refresh()->unit_type_id)->toBeNull();
});
