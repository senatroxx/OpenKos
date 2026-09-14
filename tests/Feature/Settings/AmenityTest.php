<?php

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('is owner-only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('settings.amenities.index'))
        ->assertForbidden();
});

it('lists the shared catalog for the owner', function () {
    $owner = User::factory()->owner()->create();
    Amenity::factory()->create(['name' => 'Wi-Fi']);

    $this->actingAs($owner)
        ->get(route('settings.amenities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/amenities')
            ->has('amenities', 1));
});

it('creates a reusable amenity with a generated slug', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.amenities.store'), [
            'name' => '  Rooftop Garden  ',
            'scope' => AmenityScope::Property->value,
        ])
        ->assertRedirect();

    $amenity = Amenity::query()->sole();

    expect($amenity->name)->toBe('Rooftop Garden')
        ->and($amenity->slug)->toBe('rooftop-garden')
        ->and($amenity->scope)->toBe(AmenityScope::Property);
});

it('rejects duplicate names ignoring case and surrounding whitespace', function () {
    $owner = User::factory()->owner()->create();
    Amenity::factory()->create(['name' => 'Wi-Fi']);

    $this->actingAs($owner)
        ->post(route('settings.amenities.store'), [
            'name' => '  wi-fi  ',
            'scope' => AmenityScope::Both->value,
        ])
        ->assertSessionHasErrors('name');
});

it('creates amenities with or without a supported icon', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.amenities.store'), [
            'name' => 'Rooftop Garden',
            'scope' => AmenityScope::Property->value,
            'icon' => AmenityIcon::Waves->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('settings.amenities.store'), [
            'name' => 'Reading Room',
            'scope' => AmenityScope::Property->value,
        ])
        ->assertRedirect();

    expect(Amenity::query()->where('name', 'Rooftop Garden')->value('icon'))
        ->toBe(AmenityIcon::Waves->value)
        ->and(Amenity::query()->where('name', 'Reading Room')->value('icon'))
        ->toBeNull();
});

it('rejects unsupported amenity icons', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('settings.amenities.store'), [
            'name' => 'Rooftop Garden',
            'scope' => AmenityScope::Property->value,
            'icon' => 'arbitrary-component',
        ])
        ->assertSessionHasErrors('icon');
});

it('updates an amenity without changing its slug and allows scope expansion', function () {
    $owner = User::factory()->owner()->create();
    $amenity = Amenity::factory()->create([
        'name' => 'Pool',
        'scope' => AmenityScope::Property,
        'icon' => null,
    ]);
    $originalSlug = $amenity->slug;

    $this->actingAs($owner)
        ->patch(route('settings.amenities.update', $amenity), [
            'name' => 'Swimming Pool',
            'scope' => AmenityScope::Both->value,
            'is_active' => true,
            'icon' => AmenityIcon::Waves->value,
        ])
        ->assertRedirect();

    expect($amenity->refresh()->name)->toBe('Swimming Pool')
        ->and($amenity->slug)->toBe($originalSlug)
        ->and($amenity->scope)->toBe(AmenityScope::Both)
        ->and($amenity->icon)->toBe(AmenityIcon::Waves->value);

    $this->actingAs($owner)
        ->patch(route('settings.amenities.update', $amenity), [
            'name' => 'Swimming Pool',
            'scope' => AmenityScope::Both->value,
            'is_active' => true,
            'icon' => null,
        ])
        ->assertRedirect();

    expect($amenity->refresh()->icon)->toBeNull();

    $amenity->update(['slug' => 'changed-slug']);

    expect($amenity->refresh()->slug)->toBe($originalSlug);
});

it('rejects scope narrowing when assignments would become incompatible', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $amenity = Amenity::factory()->create(['scope' => AmenityScope::Both]);
    $property->facilities()->attach($amenity);
    $unitType->amenities()->attach($amenity);

    $this->actingAs($owner)
        ->patch(route('settings.amenities.update', $amenity), [
            'name' => $amenity->name,
            'scope' => AmenityScope::Property->value,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('scope');

    expect($amenity->refresh()->scope)->toBe(AmenityScope::Both);
});

it('archives referenced amenities and reactivation preserves their assignments', function () {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $amenity = Amenity::factory()->create(['scope' => AmenityScope::Property]);
    $property->facilities()->attach($amenity);

    $this->actingAs($owner)
        ->delete(route('settings.amenities.destroy', $amenity))
        ->assertRedirect();

    expect($amenity->refresh()->is_active)->toBeFalse()
        ->and($property->fresh()->facilities->modelKeys())->toBe([$amenity->id]);

    $this->actingAs($owner)
        ->patch(route('settings.amenities.update', $amenity), [
            'name' => $amenity->name,
            'scope' => AmenityScope::Property->value,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($amenity->refresh()->is_active)->toBeTrue();
});

it('deletes only unused amenities', function () {
    $owner = User::factory()->owner()->create();
    $amenity = Amenity::factory()->create();

    $this->actingAs($owner)
        ->delete(route('settings.amenities.destroy', $amenity))
        ->assertRedirect();

    expect(Amenity::query()->whereKey($amenity->id)->exists())->toBeFalse();
});
