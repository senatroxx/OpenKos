<?php

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Enums\PropertyRentalMode;
use App\Models\Amenity;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyRate;
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
    foreach ([$property, $duplicateProperty] as $item) {
        $unitType = UnitType::factory()->for($item)->create([
            'is_published' => true,
            'public_slug' => 'studio',
        ]);
        Unit::factory()->for($item)->create(['unit_type_id' => $unitType->id]);
    }

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
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $otherProperty = Property::factory()->create();
    $unitType = UnitType::factory()->for($property)->create(['name' => 'Studio']);
    $duplicateUnitType = UnitType::factory()->for($property)->create(['name' => 'Studio.']);
    $otherUnitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Studio']);
    $foreignOnlyUnitType = UnitType::factory()->for($otherProperty)->create(['name' => 'Penthouse']);

    foreach ([$unitType, $duplicateUnitType, $otherUnitType, $foreignOnlyUnitType] as $item) {
        Unit::factory()->for($item->property)->create(['unit_type_id' => $item->id]);
    }

    foreach ([$unitType, $duplicateUnitType, $otherUnitType, $foreignOnlyUnitType] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.unit-types.publication.update', [$item->property, $item]), ['is_published' => true])
            ->assertRedirect();
    }

    foreach ([$property, $otherProperty] as $item) {
        $this->actingAs($user)
            ->patch(route('properties.publication.update', $item), ['is_published' => true])
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

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertOk();

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $foreignOnlyUnitType->public_slug,
    ]))->assertNotFound();

    expect($unitType->refresh()->public_slug)->toBe('studio');

    $property->update(['is_active' => false]);

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();

    $property->update(['is_active' => true]);
    $unitType->update(['is_active' => false]);

    $this->get(route('public.portal.unit-types.show', [
        'property' => $property->public_slug,
        'unitType' => $unitType->public_slug,
    ]))->assertNotFound();
});

it('projects availability and independent rate variants without operational details', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
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
        ->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])
        ->assertRedirect();
    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $property->refresh();
    $unitType->refresh();
    auth()->logout();

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $response = $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/listings/show')
            ->where('listing.rental_mode', PropertyRentalMode::Unit->value)
            ->where('listing.inventory.total_units', 3)
            ->where('listing.inventory.available_units', 2)
            ->where('listing.amenities', [
                ['name' => 'Legacy icon amenity', 'icon' => null],
                ['name' => 'Parking', 'icon' => 'car'],
            ])
            ->where('listing.unit_types.0.amenities', [])
            ->has('listing.unit_types.0.starting_prices', 3)
            ->missing('listing.id')
            ->missing('listing.phone')
            ->missing('listing.unit_types.0.id')
            ->missing('listing.unit_types.0.units')
            ->missing('listing.unit_types.0.leases'));

    expect($response->inertiaProps('listing.unit_types.0.starting_prices'))->toEqual([
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
    ])->and(count(DB::connection()->getQueryLog()))->toBeLessThanOrEqual(15);

    DB::connection()->flushQueryLog();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/listings/index')
            ->where('listings.0.inventory.available_units', 2));

    expect(count(DB::connection()->getQueryLog()))->toBeLessThanOrEqual(14);
});

it('requires an active property rate before whole-property publication', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
    ]);
    PropertyRate::factory()->for($property)->create(['is_active' => false]);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertUnprocessable();

    expect($property->refresh()->is_published)->toBeFalse()
        ->and($property->public_slug)->toBeNull();
});

it('requires a published unit type with eligible inventory and active pricing', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);

    expect($property->hasViableUnitTypeOffering())->toBeFalse();

    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $unit->rates()->update(['is_active' => false]);

    expect($property->refresh()->hasViableUnitTypeOffering())->toBeFalse();

    $unit->rates()->firstOrFail()->update(['is_active' => true]);

    expect($property->refresh()->hasViableUnitTypeOffering())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();
});

it('removes a unit listing when its last active unit rate is disabled', function () {
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
        'public_slug' => 'rate-disabled-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 1));

    $unit->rates()->update(['is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 0));

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertNotFound();
});

it('publishes whole-property offerings without unit inventory', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
    ]);
    PropertyRate::factory()->for($property)->create([
        'billing_unit' => 'month',
        'amount' => '15000000',
        'currency' => 'IDR',
    ]);
    PropertyRate::factory()->for($property)->create([
        'billing_unit' => 'week',
        'amount' => '12000000',
        'currency' => 'USD',
    ]);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $property->refresh();

    $response = $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/listings/show')
            ->where('listing.rental_mode', PropertyRentalMode::WholeProperty->value)
            ->where('listing.whole_property_offering.type', PropertyRentalMode::WholeProperty->value)
            ->where('listing.whole_property_offering.availability', 'available_for_inquiry')
            ->where('listing.whole_property_offering.starting_price.amount', '15000000.000')
            ->where('listing.whole_property_offering.starting_price.currency', 'IDR')
            ->has('listing.whole_property_offering.rates', 2)
            ->where('listing.whole_property_offering.rates.0.billing_unit', 'week')
            ->where('listing.whole_property_offering.rates.0.currency', 'USD')
            ->where('listing.whole_property_offering.rates.1.billing_unit', 'month')
            ->where('listing.whole_property_offering.rates.1.currency', 'IDR')
            ->missing('listing.inventory')
            ->missing('listing.unit_types')
            ->missing('listing.whole_property_offering.starting_price.id'));

    expect($response->inertiaProps('listing.whole_property_offering.rates.0'))->toHaveKeys([
        'amount',
        'currency',
        'billing_interval',
        'billing_unit',
        'billing_label',
    ]);
    expect($response->inertiaProps('listing.whole_property_offering.rates.0'))->not->toHaveKey('id');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listings.0.slug', $property->public_slug)
            ->where('listings.0.whole_property_offering.starting_price.amount', '15000000.000')
            ->missing('listings.0.inventory')
            ->missing('listings.0.unit_types'));

    Lease::factory()->wholeProperty($property)->create([
        'property_rate_id' => $property->activePropertyRates()->firstOrFail()->id,
    ]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.whole_property_offering.availability', 'unavailable'));
});

it('restores whole-property visibility when an active rate is re-enabled', function () {
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
        'public_slug' => 'whole-house',
        'is_published' => true,
    ]);
    $rate = PropertyRate::factory()->for($property)->create(['is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 1));

    $rate->update(['is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 0));

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertNotFound();

    $rate->update(['is_active' => true]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk();
});

it('exposes hybrid offerings independently', function () {
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Hybrid,
        'public_slug' => 'hybrid-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.rental_mode', PropertyRentalMode::Hybrid->value)
            ->where('listing.unit_types.0.slug', $unitType->public_slug)
            ->missing('listing.whole_property_offering'));

    $rate = PropertyRate::factory()->for($property)->create([
        'amount' => '2500000',
        'currency' => 'IDR',
    ]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.whole_property_offering.type', PropertyRentalMode::WholeProperty->value)
            ->where('listing.unit_types.0.slug', $unitType->public_slug));

    Lease::factory()->create(['unit_id' => $unit->id]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.whole_property_offering.availability', 'unavailable')
            ->where('listing.unit_types.0.inventory.available_units', 0));

    $unitType->update(['is_published' => false]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.whole_property_offering.type', PropertyRentalMode::WholeProperty->value)
            ->missing('listing.inventory')
            ->missing('listing.unit_types'));

    $rate->update(['is_active' => false]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertNotFound();
});

it('omits non-viable unit metadata from a hybrid whole-property-only listing', function () {
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Hybrid,
        'public_slug' => 'hybrid-whole-only-house',
        'is_published' => true,
    ]);
    PropertyRate::factory()->for($property)->create();
    UnitType::factory()->for($property)->create([
        'public_slug' => 'unavailable-studio',
        'is_published' => true,
    ]);

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.whole_property_offering.type', PropertyRentalMode::WholeProperty->value)
            ->missing('listing.inventory')
            ->missing('listing.unit_types'));
});

it('fails closed when a hybrid property has no viable offering path', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Hybrid,
        'public_slug' => 'empty-hybrid-house',
        'is_published' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertUnprocessable();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 0));

    $this->get(route('public.portal.show', $property->public_slug))
        ->assertNotFound();
});

it('uses whole-property readiness for public property media', function () {
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
        'public_slug' => 'whole-house',
        'is_published' => true,
    ]);
    $rate = PropertyRate::factory()->for($property)->create();
    $media = app(MediaManager::class)->store(
        $property,
        'photos',
        UploadedFile::fake()->create('whole-house.jpg', 1, 'image/jpeg'),
    );

    $this->get(route('public.portal.media', $media))
        ->assertSuccessful();

    $rate->update(['is_active' => false]);

    $this->get(route('public.portal.media', $media))
        ->assertNotFound();
});

it('serves public media only for currently effective listings', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $unitType = UnitType::factory()->for($property)->create();
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $this->actingAs($user)
        ->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])
        ->assertRedirect();
    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $manager = app(MediaManager::class);
    $propertyMedia = $manager->store($property, 'photos', UploadedFile::fake()->create('property.jpg', 1, 'image/jpeg'));
    $unitTypeMedia = $manager->store($unitType, 'photos', UploadedFile::fake()->create('unit-type.jpg', 1, 'image/jpeg'));
    $attachment = $manager->store($property, 'attachments', UploadedFile::fake()->create('private.pdf', 1, 'application/pdf'));
    auth()->logout();

    $propertyMediaResponse = $this->get(route('public.portal.media', $propertyMedia))
        ->assertSuccessful();
    $unitTypeMediaResponse = $this->get(route('public.portal.media', $unitTypeMedia))
        ->assertSuccessful();

    expect($propertyMediaResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and($unitTypeMediaResponse->headers->get('Cache-Control'))->toContain('no-store');
    $this->get(route('public.portal.media', $attachment))->assertNotFound();

    $property->refresh()->update(['is_published' => false]);

    $this->get(route('public.portal.media', $propertyMedia))->assertNotFound();
    $this->get(route('public.portal.media', $unitTypeMedia))->assertNotFound();
});

it('renders the public storefront at the root for guests and authenticated users', function () {
    config(['inertia.ssr.enabled' => false]);

    $property = Property::factory()->create([
        'public_slug' => 'sunrise-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'public_slug' => 'studio',
        'is_published' => true,
    ]);
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

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

it('does not expose a public listing JSON API', function () {
    $this->getJson('/api/v1/listings')->assertNotFound();
    $this->getJson('/api/v1/listings/media/1')->assertNotFound();
    $this->getJson('/api/v1/listings/example')->assertNotFound();
    $this->getJson('/api/v1/listings/example/unit-types/studio')->assertNotFound();
});

it('keeps storefront metadata distinct from entity detail metadata', function () {
    $homepage = file_get_contents(resource_path('js/pages/public/listings/index.tsx'));
    $head = file_get_contents(resource_path('js/components/shared/public-listing-head.tsx'));
    $propertyDetail = file_get_contents(resource_path('js/pages/public/listings/show.tsx'));
    $unitTypeDetail = file_get_contents(resource_path('js/pages/public/listings/unit-type.tsx'));

    expect($homepage)
        ->toContain("title={t('OpenKOS — Find Your Next Place')}")
        ->toContain("'Discover available properties and rental options that fit your needs.'")
        ->not->toContain("title={t('Available properties')}");

    expect($head)
        ->toContain('head-key="og:title"')
        ->toContain('head-key="og:description"')
        ->toContain('head-key="twitter:title"')
        ->toContain('head-key="twitter:description"')
        ->toContain('head-key="canonical"');

    expect($propertyDetail)->toContain('title={listing.name}');
    expect($unitTypeDetail)->toContain('title={`${unitType.name} - ${listing.property.name}`}');
});

it('keeps listing recommendations non-blocking for a viable offering', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
        'description' => null,
        'address' => null,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'studio',
    ]);

    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    Unit::factory()->for($property)->create(['unit_type_id' => null]);

    $response = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk();

    $readiness = $response->inertiaProps('readiness');

    expect($readiness['can_publish'])->toBeTrue()
        ->and($readiness['is_published'])->toBeFalse()
        ->and($readiness['is_publicly_visible'])->toBeFalse()
        ->and($readiness['public_url'])->toBeNull()
        ->and($readiness['blockers'])->toBeEmpty()
        ->and(collect($readiness['recommendations'])->pluck('key')->all())
        ->toContain('description', 'photos', 'amenities', 'unassigned_units', 'property_information');

    expect(collect($readiness['recommendations'])->firstWhere('key', 'unassigned_units')['action']['url'])
        ->toContain('assignment=unassigned');

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    expect($property->refresh()->is_published)->toBeTrue();
});

it('separates published state from current public visibility', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
        'public_slug' => 'temporarily-hidden-house',
        'is_published' => true,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'studio',
    ]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $unit->rates()->update(['is_active' => false]);

    $response = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk();

    $readiness = $response->inertiaProps('readiness');

    expect($readiness['can_publish'])->toBeFalse()
        ->and($readiness['is_published'])->toBeTrue()
        ->and($readiness['is_publicly_visible'])->toBeFalse()
        ->and($readiness['public_url'])->toBeNull()
        ->and(collect($readiness['blockers'])->pluck('key')->all())
        ->toContain('unit_rate_required')
        ->and($readiness['unit_types'][0]['status'])->toBe('blocked');
});

it('does not treat zero current availability as listing failure', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'occupied-studio',
    ]);
    $unit = Unit::factory()->for($property)->create([
        'unit_type_id' => $unitType->id,
        'status' => 'occupied',
    ]);
    Lease::factory()->create(['unit_id' => $unit->id]);

    $response = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk();

    $readiness = $response->inertiaProps('readiness');

    expect($readiness['can_publish'])->toBeTrue()
        ->and($readiness['blockers'])->toBeEmpty()
        ->and($readiness['unit_types'][0]['available_units'])->toBe(0)
        ->and($readiness['unit_types'][0]['status'])->toBe('ready');
});

it('exposes whole-property rental options without changing publication rules', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::WholeProperty,
    ]);
    PropertyRate::factory()->for($property)->create([
        'amount' => '1500000',
        'currency' => 'IDR',
    ]);

    $response = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk();

    $readiness = $response->inertiaProps('readiness');

    expect($readiness['can_publish'])->toBeTrue()
        ->and($readiness['whole_property']['is_listed'])->toBeFalse()
        ->and($readiness['whole_property']['has_active_pricing'])->toBeTrue()
        ->and($readiness['whole_property']['starting_price']['amount'])->toBe('1500000.000')
        ->and($readiness['unit_types'])->toBeEmpty();

    $this->actingAs($user)
        ->patch(route('properties.publication.update', $property), ['is_published' => true])
        ->assertRedirect();

    $readiness = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->inertiaProps('readiness');

    expect($readiness['is_published'])->toBeTrue()
        ->and($readiness['is_publicly_visible'])->toBeTrue()
        ->and($readiness['whole_property']['is_listed'])->toBeTrue();
});

it('keeps listed unit type inclusion separate from current viability', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'studio',
    ]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $unit->rates()->update(['is_active' => false]);

    $readiness = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk()
        ->inertiaProps('readiness');

    expect($readiness['unit_types'][0]['is_included'])->toBeTrue()
        ->and($readiness['unit_types'][0]['status'])->toBe('blocked')
        ->and($readiness['unit_types'][0]['reason'])->toBe('No active pricing is configured for these Units.');
});

it('shows whole-property and unit rental options independently for hybrid properties', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Hybrid,
    ]);
    PropertyRate::factory()->for($property)->create();
    $unitType = UnitType::factory()->for($property)->create([
        'is_published' => true,
        'public_slug' => 'studio',
    ]);
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $readiness = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk()
        ->inertiaProps('readiness');

    expect($readiness['whole_property'])->not->toBeNull()
        ->and($readiness['whole_property']['has_active_pricing'])->toBeTrue()
        ->and($readiness['unit_types'])->toHaveCount(1)
        ->and($readiness['unit_types'][0]['is_included'])->toBeTrue();
});

it('presents eligible excluded types without misleading setup warnings', function (string $route, string $key) {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['rental_mode' => PropertyRentalMode::Unit]);
    $unitType = UnitType::factory()->for($property)->create(['is_published' => false, 'public_slug' => null]);
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

    $options = $this->actingAs($user)->get(route($route, $property))->assertOk()->inertiaProps($key);

    expect($options[0]['is_included'])->toBeFalse()
        ->and($options[0]['is_viable_if_included'])->toBeTrue()
        ->and($options[0]['status'])->toBe('excluded')
        ->and($options[0]['reason'])->toBeNull()
        ->and($options[0]['physical_units'])->toBe(1)
        ->and($options[0]['available_units'])->toBe(1)
        ->and($options[0]['starting_price'])->not->toBeNull();

    $this->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => true])->assertRedirect();
    $unit->rates()->update(['is_active' => false]);

    $options = $this->get(route($route, $property))->assertOk()->inertiaProps($key);
    expect($options[0]['is_included'])->toBeTrue()
        ->and($options[0]['status'])->toBe('blocked')
        ->and($options[0]['has_active_pricing'])->toBeFalse();

    $unitType->update(['is_active' => false]);
    $options = $this->get(route($route, $property))->assertOk()->inertiaProps($key);
    expect($options[0]['is_included'])->toBeTrue()->and($options[0]['status'])->toBe('inactive');

    $this->patch(route('properties.unit-types.publication.update', [$property, $unitType]), ['is_published' => false])->assertRedirect();
    expect($unitType->refresh()->is_published)->toBeFalse();
})->with([
    ['properties.listing', 'readiness.unit_types'],
    ['properties.unit-types.index', 'rentalOptions'],
]);

it('explains why an excluded incomplete unit type cannot be included', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    UnitType::factory()->for($property)->create([
        'is_published' => false,
        'public_slug' => null,
    ]);

    $readiness = $this->actingAs($user)
        ->get(route('properties.listing', $property))
        ->assertOk()
        ->inertiaProps('readiness');

    expect($readiness['unit_types'][0]['is_included'])->toBeFalse()
        ->and($readiness['unit_types'][0]['is_viable_if_included'])->toBeFalse()
        ->and($readiness['unit_types'][0]['status'])->toBe('blocked')
        ->and($readiness['unit_types'][0]['reason'])->toBe('No Units are assigned to this Unit Type.')
        ->and($readiness['unit_types'][0]['action']['label'])->toBe('Open Units');
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
    Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);

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
