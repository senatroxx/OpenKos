<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\Listings\PublicSlugAllocator;
use App\Services\Media\MediaManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
});

it('publishes properties with stable public slugs', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['name' => 'Sunrise House']);
    $duplicateProperty = Property::factory()->create(['name' => 'Sunrise House']);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $publicSlug = $property->refresh()->public_slug;

    expect($property->is_published)->toBeTrue()
        ->and($publicSlug)->toBe('sunrise-house');

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $duplicateProperty), ['is_published' => true])
        ->assertRedirect();

    expect($duplicateProperty->refresh()->public_slug)->toBe('sunrise-house-1');

    $property->update(['name' => 'Sunset House']);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => false])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    expect($property->refresh()->public_slug)->toBe($publicSlug);
});

it('retries a public slug transaction after a unique-key collision', function () {
    Property::factory()->create(['public_slug' => 'collision']);
    $attempts = 0;

    $result = app(PublicSlugAllocator::class)->transaction(function () use (&$attempts): string {
        $attempts++;

        if ($attempts === 1) {
            Property::factory()->create(['public_slug' => 'collision']);
        }

        return 'completed';
    });

    expect($result)->toBe('completed')->and($attempts)->toBe(2);
});

it('allocates property-scoped unit type slugs and derives effective visibility', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $duplicateUnitType = UnitType::factory()->for($property)->create(['name' => 'Studio.']);
    $otherUnitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);

    foreach ([$property, $otherProperty] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.publication.update', $item), ['is_published' => true])
            ->assertRedirect();
    }

    foreach ([$unitType, $duplicateUnitType, $otherUnitType] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.unit-types.publication.update', [$item->property, $item]), ['is_published' => true])
            ->assertRedirect();
    }

    $property->refresh();
    $otherProperty->refresh();
    $unitType->refresh();
    $duplicateUnitType->refresh();
    $otherUnitType->refresh();

    expect($unitType->refresh()->public_slug)->toBe('studio')
        ->and($duplicateUnitType->refresh()->public_slug)->toBe('studio-1')
        ->and($otherUnitType->refresh()->public_slug)->toBe('studio');

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => false])
        ->assertRedirect();

    expect($unitType->refresh()->is_published)->toBeTrue();

    $unitType->update(['name' => 'Loft']);

    auth()->logout();

    $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertSuccessful();

    expect($unitType->refresh()->public_slug)->toBe('studio');

    $property->update(['is_active' => false]);

    $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();

    $property->update(['is_active' => true]);
    $unitType->update(['is_active' => false]);

    $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();
});

it('projects availability and independent rate variants without operational details', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $available = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $full = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 1,
        'status' => 'occupied',
    ]);
    $shared = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'capacity' => 2,
        'status' => 'occupied',
    ]);

    Lease::factory()->create(['unit_id' => $full->id]);
    Lease::factory()->create(['unit_id' => $shared->id]);
    $available->rates()->where('billing_unit', 'month')->update(['amount' => '10000.00']);
    $available->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'amount' => '900.00',
        'currency' => 'USD',
    ]);
    $available->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'year',
        'amount' => '10000.00',
        'currency' => 'IDR',
    ]);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();
    $this->actingAs($user)
        ->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])
        ->assertRedirect();

    $property->refresh();
    $unitType->refresh();
    auth()->logout();

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $response = $this->getJson(route('public.listings.show', $property->public_slug))
        ->assertSuccessful()
        ->assertJsonPath('data.inventory.total_units', 3)
        ->assertJsonPath('data.inventory.available_units', 2)
        ->assertJsonCount(3, 'data.unit_types.0.starting_prices')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.phone')
        ->assertJsonMissingPath('data.unit_types.0.units')
        ->assertJsonMissingPath('data.unit_types.0.leases');

    expect($response->json('data.unit_types.0.starting_prices'))->toEqual([
        [
            'amount' => '10000.000',
            'currency' => 'IDR',
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'billing_label' => '/month',
        ],
        [
            'amount' => '10000.000',
            'currency' => 'IDR',
            'billing_interval' => 1,
            'billing_unit' => 'year',
            'billing_label' => '/year',
        ],
        [
            'amount' => '900.000',
            'currency' => 'USD',
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'billing_label' => '/month',
        ],
    ])->and(count(DB::connection()->getQueryLog()))->toBeLessThanOrEqual(12);

    DB::connection()->flushQueryLog();

    $this->getJson(route('public.listings.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.inventory.available_units', 2);

    expect(count(DB::connection()->getQueryLog()))->toBeLessThanOrEqual(12);
});

it('serves public media only for currently effective listings', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();
    $this->actingAs($user)
        ->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])
        ->assertRedirect();

    $manager = app(MediaManager::class);
    $propertyMedia = $manager->store($property, 'photos', UploadedFile::fake()->create('property.jpg', 1, 'image/jpeg'));
    $unitTypeMedia = $manager->store($unitType, 'photos', UploadedFile::fake()->create('unit-type.jpg', 1, 'image/jpeg'));
    $attachment = $manager->store($property, 'attachments', UploadedFile::fake()->create('private.pdf', 1, 'application/pdf'));
    auth()->logout();

    $this->get(route('public.listings.media', $propertyMedia))->assertSuccessful();
    $this->get(route('public.listings.media', $unitTypeMedia))->assertSuccessful();
    $this->get(route('public.listings.media', $attachment))->assertNotFound();

    $property->refresh()->update(['is_published' => false]);

    $this->get(route('public.listings.media', $propertyMedia))->assertNotFound();
    $this->get(route('public.listings.media', $unitTypeMedia))->assertNotFound();
});
