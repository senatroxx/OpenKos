<?php

use App\Enums\PropertyRentalMode;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('is owner-only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('settings.property-types.index'))
        ->assertForbidden();
});

it('lists the seeded types for the owner', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('settings.property-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/property-types')
            ->has('propertyTypes', 5)
            ->where('propertyTypes.2.default_rental_mode', PropertyRentalMode::WholeProperty->value)); // boarding_house, apartment, villa, hostel, hotel
});

it('creates a type with a generated slug', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('settings.property-types.store'), [
        'label' => 'Riad',
        'default_rental_mode' => PropertyRentalMode::Hybrid->value,
    ]);

    expect(PropertyType::where('slug', 'riad')
        ->where('label', 'Riad')
        ->where('default_rental_mode', PropertyRentalMode::Hybrid)
        ->exists())->toBeTrue();
});

it('rejects a duplicate label', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.property-types.store'), ['label' => 'Villa'])
        ->assertSessionHasErrors('label');
});

it('updates a label without changing the slug', function () {
    $owner = User::factory()->owner()->create();
    $type = PropertyType::where('slug', 'hostel')->first();

    $this->actingAs($owner)->patch(route('settings.property-types.update', $type), [
        'label' => 'Backpacker Hostel',
        'is_active' => false,
        'default_rental_mode' => PropertyRentalMode::Unit->value,
    ]);

    $type->refresh();

    expect($type->slug)->toBe('hostel')
        ->and($type->label)->toBe('Backpacker Hostel')
        ->and($type->is_active)->toBeFalse();
});

it('updates a type default rental model without changing its slug', function () {
    $owner = User::factory()->owner()->create();
    $type = PropertyType::where('slug', 'hostel')->first();

    $this->actingAs($owner)->patch(route('settings.property-types.update', $type), [
        'label' => $type->label,
        'default_rental_mode' => PropertyRentalMode::Hybrid->value,
    ]);

    expect($type->refresh()->default_rental_mode)->toBe(PropertyRentalMode::Hybrid);
});

it('does not apply a changed type default to existing properties', function () {
    $owner = User::factory()->owner()->create();
    $type = PropertyType::where('slug', 'villa')->first();
    $property = Property::factory()->create([
        'type' => 'villa',
        'rental_mode' => PropertyRentalMode::Unit,
    ]);

    $this->actingAs($owner)->patch(route('settings.property-types.update', $type), [
        'label' => $type->label,
        'default_rental_mode' => PropertyRentalMode::Hybrid->value,
    ]);

    expect($property->refresh()->rental_mode)->toBe(PropertyRentalMode::Unit);
});

it('rejects an invalid type default rental model', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.property-types.store'), [
            'label' => 'Invalid Type',
            'default_rental_mode' => 'rooms_only',
        ])
        ->assertSessionHasErrors('default_rental_mode');
});

it('deletes an unused type', function () {
    $owner = User::factory()->owner()->create();
    $type = PropertyType::factory()->create();

    $this->actingAs($owner)->delete(route('settings.property-types.destroy', $type));

    expect(PropertyType::find($type->id))->toBeNull();
});

it('refuses to delete a type that is in use', function () {
    $owner = User::factory()->owner()->create();
    $type = PropertyType::factory()->create(['slug' => 'guesthouse']);
    Property::factory()->create(['type' => 'guesthouse']);

    $this->actingAs($owner)->delete(route('settings.property-types.destroy', $type));

    expect(PropertyType::find($type->id))->not->toBeNull();
});
