<?php

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Models\Amenity;
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

it('rejects publishing inactive properties and unit types', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['is_active' => false]);
    $unitType = UnitType::factory()->for($property)->create(['is_active' => false]);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])
        ->assertUnprocessable();

    expect($property->refresh()->is_published)->toBeFalse()
        ->and($unitType->refresh()->is_published)->toBeFalse();
});

it('allocates property-scoped unit type slugs and derives effective visibility', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $duplicateUnitType = UnitType::factory()->for($property)->create(['name' => 'Studio.']);
    $otherUnitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);
    $foreignOnlyUnitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Penthouse']);

    foreach ([$property, $otherProperty] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.publication.update', $item), ['is_published' => true])
            ->assertRedirect();
    }

    foreach ([$unitType, $duplicateUnitType, $otherUnitType, $foreignOnlyUnitType] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.unit-types.publication.update', [$item->property, $item]), ['is_published' => true])
            ->assertRedirect();
    }

    $property->refresh();
    $otherProperty->refresh();
    $unitType->refresh();
    $duplicateUnitType->refresh();
    $otherUnitType->refresh();
    $foreignOnlyUnitType->refresh();

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

    $unitTypeResponse = $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertSuccessful();

    expect($unitTypeResponse->headers->get('Cache-Control'))->toContain('no-store');

    $this->getJson(route('public.listings.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $foreignOnlyUnitType->public_slug,
    ]))->assertNotFound();

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
    $propertyAmenity = Amenity::factory()->create([
        'name' => 'Parking',
        'scope' => AmenityScope::Property,
        'icon' => AmenityIcon::Car->value,
    ]);
    $legacyIconAmenity = Amenity::factory()->create([
        'name' => 'Legacy icon amenity',
        'scope' => AmenityScope::Property,
        'icon' => 'legacy-markup',
    ]);
    $inactivePropertyAmenity = Amenity::factory()->create([
        'name' => 'Closed gym',
        'scope' => AmenityScope::Property,
    ]);
    $inactiveUnitTypeAmenity = Amenity::factory()->create([
        'name' => 'Air conditioning',
        'scope' => AmenityScope::UnitType,
    ]);
    $property->facilities()->attach([$propertyAmenity, $legacyIconAmenity, $inactivePropertyAmenity]);
    $inactivePropertyAmenity->update(['is_active' => false]);
    $unitType->amenities()->attach($inactiveUnitTypeAmenity);
    $inactiveUnitTypeAmenity->update(['is_active' => false]);
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
        ->assertJsonPath('data.amenities', [
            ['name' => 'Legacy icon amenity', 'icon' => null],
            ['name' => 'Parking', 'icon' => 'car'],
        ])
        ->assertJsonPath('data.unit_types.0.amenities', [])
        ->assertJsonCount(3, 'data.unit_types.0.starting_prices')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.phone')
        ->assertJsonMissingPath('data.unit_types.0.id')
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

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    DB::connection()->flushQueryLog();

    $indexResponse = $this->getJson(route('public.listings.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.inventory.available_units', 2);

    expect($indexResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(count(DB::connection()->getQueryLog()))->toBeLessThanOrEqual(12);
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

    $propertyMediaResponse = $this->get(route('public.listings.media', $propertyMedia))
        ->assertSuccessful();
    $unitTypeMediaResponse = $this->get(route('public.listings.media', $unitTypeMedia))
        ->assertSuccessful();

    expect($propertyMediaResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and($unitTypeMediaResponse->headers->get('Cache-Control'))->toContain('no-store');
    $this->get(route('public.listings.media', $attachment))->assertNotFound();

    $property->refresh()->update(['is_published' => false]);

    $this->get(route('public.listings.media', $propertyMedia))->assertNotFound();
    $this->get(route('public.listings.media', $unitTypeMedia))->assertNotFound();
});

it('renders the public storefront at the root for guests and authenticated users', function () {
    config(['inertia.ssr.enabled' => false]);

    $property = Property::factory()->create([
        'public_slug' => 'sunrise-house',
        'is_published' => true,
    ]);

    $assertStorefront = function ($response) use ($property): void {
        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/listings/index')
                ->where('canonicalUrl', '/')
                ->where('listings.0.slug', $property->public_slug)
                ->missing('listings.0.id')
                ->missing('listings.0.phone'));
    };

    $assertStorefront($this->get('/'));
    $assertStorefront($this->actingAs(User::factory()->owner()->create())->get('/'));
});

it('keeps login explicit and redirects the legacy listing index permanently', function () {
    config(['inertia.ssr.enabled' => false]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));

    $this->get('/listings')
        ->assertStatus(308)
        ->assertLocation('/');
});

it('renders public property and unit type pages from the safe listing projection', function () {
    config(['inertia.ssr.enabled' => false]);

    $property = Property::factory()->create([
        'public_slug' => 'sunrise-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);

    $this->get(route('public.portal.show', ['property' => $property->public_slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/listings/show')
            ->where('canonicalUrl', route('public.portal.show', ['property' => $property->public_slug], absolute: false))
            ->where('listing.slug', $property->public_slug)
            ->where('listing.unit_types.0.slug', $unitType->public_slug)
            ->missing('listing.id')
            ->missing('listing.phone')
            ->missing('listing.unit_types.0.id')
            ->missing('listing.unit_types.0.units')
            ->missing('listing.unit_types.0.leases'));

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/listings/unit-type')
            ->where('canonicalUrl', route('public.portal.unit-types.show', [
                'property' => $property->public_slug,
                'unitType' => $unitType->public_slug,
            ], absolute: false))
            ->where('listing.property.slug', $property->public_slug)
            ->where('listing.unit_type.slug', $unitType->public_slug)
            ->missing('listing.unit_type.id')
            ->missing('listing.unit_type.units')
            ->missing('listing.unit_type.leases'));
});

it('fails closed for unpublished and mismatched public detail pages', function () {
    config(['inertia.ssr.enabled' => false]);

    $property = Property::factory()->create([
        'public_slug' => 'sunrise-house',
        'is_published' => false,
    ]);
    $otherProperty = Property::factory()->create([
        'public_slug' => 'sunset-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($otherProperty)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);

    $this->get(route('public.portal.show', ['property' => $property->public_slug]))
        ->assertNotFound();

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();

    $this->get(route('public.portal.unit-types.show', [
        'property' => $otherProperty->public_slug,
        'unitType' => 'missing',
    ]))->assertNotFound();
});
