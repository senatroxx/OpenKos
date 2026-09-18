<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\UnitTypeRate;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
            ->has('unitTypes.data', 1)
            ->where('unitTypes.data.0.name', 'Studio')
        );
});

it('opens the Unit Type workspace with overview data and direct tabs', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['name' => 'Kos Anggrek Residence']);
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    UnitTypeRate::factory()->for($unitType)->create(['amount' => '1500000']);
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->actingAs($user)
        ->get(route('properties.unit-types.show', [$property, $unitType]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('properties/unit-types/show')
            ->where('unitType.id', $unitType->id)
            ->where('unitType.units_count', 1)
            ->where('unitType.active_rates.0.amount', '1500000.000')
            ->where('listing.id', $unitType->id)
        );

    $this->actingAs($user)
        ->get(route('properties.unit-types.rates.index', [$property, $unitType]))
        ->assertInertia(fn ($page) => $page->component('properties/unit-types/rates'));

    $this->actingAs($user)
        ->get(route('properties.unit-types.listing', [$property, $unitType]))
        ->assertInertia(fn ($page) => $page->component('properties/unit-types/listing'));
});

it('rejects a Unit Type workspace from another property', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $unitType = UnitType::factory()->for($otherProperty)->create();

    $this->actingAs($user)
        ->get(route('properties.unit-types.show', [$property, $unitType]))
        ->assertNotFound();
});

it('keeps inactive Unit Types readable but hides deleted workspaces', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $inactive = UnitType::factory()->for($property)->create(['name' => 'Inactive Studio', 'is_active' => false]);
    $deleted = UnitType::factory()->for($property)->create(['name' => 'Deleted Studio']);
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('properties.unit-types.show', [$property, $inactive]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unitType.is_active', false));

    $this->actingAs($user)
        ->get(route('properties.unit-types.show', [$property, $deleted]))
        ->assertNotFound();
});

it('scopes the existing Units workspace table to a Unit Type', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create();
    $included = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    Unit::factory()->for($property)->create();

    $this->actingAs($user)
        ->get(route('properties.unit-types.units', [$property, $unitType]))
        ->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->where('unitTypeWorkspace.id', $unitType->id)
            ->where('units.total', 1)
            ->where('units.data.0.id', $included->id)
        );
});

it('searches, filters, paginates, and soft deletes UnitTypes', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $active = UnitType::factory()->for($property)->create(['name' => 'Deluxe Studio']);
    UnitType::factory()->for($property)->create(['name' => 'Inactive Studio', 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('properties.unit-types.index', [$property, 'search' => 'deluxe']))
        ->assertInertia(fn ($page) => $page
            ->where('unitTypes.total', 1)
            ->where('unitTypes.data.0.id', $active->id)
        );

    $this->actingAs($user)
        ->get(route('properties.unit-types.index', [$property, 'status' => 'inactive']))
        ->assertInertia(fn ($page) => $page
            ->where('unitTypes.total', 1)
            ->where('unitTypes.data.0.name', 'Inactive Studio')
        );

    $this->actingAs($user)
        ->delete(route('properties.unit-types.destroy', [$property, $active]))
        ->assertRedirect(route('properties.unit-types.index', $property));

    expect(UnitType::withTrashed()->find($active->id)?->deleted_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('properties.unit-types.index', [$property, 'status' => 'deleted']))
        ->assertInertia(fn ($page) => $page
            ->where('unitTypes.total', 1)
            ->where('unitTypes.data.0.id', $active->id)
        );
});

it('restores a soft-deleted UnitType', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create();
    $unitType->delete();

    $this->actingAs($user)
        ->post(route('properties.unit-types.restore', [$property, $unitType]))
        ->assertRedirect();

    expect($unitType->refresh()->deleted_at)->toBeNull();
});

it('denies restoring a UnitType without property update permission', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create();
    $unitType->delete();

    $this->actingAs($user)
        ->post(route('properties.unit-types.restore', [$property, $unitType]))
        ->assertForbidden();
});

it('loads rental option summaries with bounded queries', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => 'unit']);
    $unitType = UnitType::factory()->for($property)->create();
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $this->actingAs($user)->get(route('properties.unit-types.index', $property))->assertOk();

    $countQueries = function () use ($property): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('properties.unit-types.index', $property))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $singleCount = $countQueries();
    UnitType::factory()->count(5)->sequence(fn ($sequence) => ['name' => 'Query test '.$sequence->index])->for($property)->create()->each(function (UnitType $type) use ($property): void {
        Unit::factory()->for($property)->create(['unit_type_id' => $type->id]);
    });

    expect($countQueries())->toBe($singleCount);
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

it('stores furnishing as a stable enum value', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), [
            'name' => 'Furnished Studio',
            'furnishing' => 'furnished',
        ])
        ->assertRedirect();

    expect(UnitType::query()->sole()->furnishing)->toBe('furnished');

    $this->actingAs($user)
        ->post(route('properties.unit-types.store', $property), [
            'name' => 'Invalid Studio',
            'furnishing' => '0',
        ])
        ->assertSessionHasErrors('furnishing');
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
