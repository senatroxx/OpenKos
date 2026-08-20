<?php

use App\Models\City;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Region;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $this->get(route('properties.index'))->assertRedirect('login');
    });

    it('returns 403 for users without properties.view permission', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });

    it('allows admin to access properties', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('allows owner to access properties', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk();
    });

    it('denies staff access to properties', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertForbidden();
    });
});

describe('CRUD', function () {
    it('lists properties on the index page', function () {
        $user = User::factory()->owner()->create();
        Property::factory()->create(['name' => 'Alpha Residence']);
        Property::factory()->create(['name' => 'Beta Residence']);
        $villa = Property::factory()->create(['name' => 'Villa Bali', 'type' => 'villa']);

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/index')
                ->has('properties.data', 3)
                ->where('properties.data.2.type_label', 'Villa')
                ->where('properties.data.2.city.id', $villa->city_id)
                ->where('properties.data.2.region.id', $villa->region_id)
            );
    });

    it('does not automatically load property relations for light queries', function () {
        Property::factory()->create();

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $property = Property::query()->get(['id', 'name'])->first();

        expect($property)->not->toBeNull()
            ->and($property->relationLoaded('region'))->toBeFalse()
            ->and($property->relationLoaded('city'))->toBeFalse()
            ->and($property->relationLoaded('propertyType'))->toBeFalse()
            ->and(DB::connection()->getQueryLog())->toHaveCount(1);
    });

    it('keeps scalar property queries relation-free', function () {
        Property::factory()->create();

        DB::connection()->enableQueryLog();

        foreach ([
            fn () => Property::query()->pluck('id'),
            fn () => Property::query()->count(),
            fn () => Property::query()->exists(),
        ] as $query) {
            DB::connection()->flushQueryLog();
            $query();

            expect(DB::connection()->getQueryLog())->toHaveCount(1);
        }
    });

    it('creates a property', function () {
        $user = User::factory()->owner()->create();
        $region = Region::where('name', 'DKI Jakarta')->first();
        $city = City::where('name', 'Kota Jakarta Selatan')->first();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Kos Melati',
            'region_id' => $region->id,
            'city_id' => $city->id,
        ]);

        $property = Property::with('region', 'city')->first();

        expect($property)->not->toBeNull();
        expect($property->name)->toBe('Kos Melati');
        expect($property->region->name)->toBe('DKI Jakarta');
        expect($property->city->name)->toBe('Kota Jakarta Selatan');
    });

    it('assigns a created property to a non-owner creator', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [
                'name' => 'Kos Melati',
            ])
            ->assertRedirect();

        $property = Property::firstOrFail();

        $this->assertDatabaseHas('property_user', [
            'user_id' => $user->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($user)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/index')
                ->has('properties.data', 1)
                ->where('properties.data.0.id', $property->id));

        $this->actingAs($user)
            ->get(route('properties.show', $property))
            ->assertOk();
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [])
            ->assertSessionHasErrors('name');
    });

    it('updates a property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['name' => 'Kos Melati']);

        $this->actingAs($user)
            ->put(route('properties.update', $property), [
                'name' => 'Kos Mawar',
            ]);

        $property->refresh();

        expect($property->name)->toBe('Kos Mawar');
    });

    it('archives a property by setting is_active to false', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)->delete(route('properties.destroy', $property));

        $property->refresh();

        expect($property->is_active)->toBeFalse();
    });
});

describe('type', function () {
    it('defaults to boarding_house when no type is given', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Sunrise Residence',
        ]);

        expect(Property::first()->type)->toBe('boarding_house');
    });

    it('stores an explicit seeded type and exposes its label', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Villa Bali',
            'type' => 'villa',
        ]);

        $property = Property::with('propertyType')->first();

        expect($property->type)->toBe('villa')
            ->and($property->type_label)->toBe('Villa');
    });

    it('falls back to the raw type without lazy loading propertyType', function () {
        $property = Property::factory()->create(['type' => 'villa']);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        expect($property->type_label)->toBe('villa')
            ->and($property->relationLoaded('propertyType'))->toBeFalse()
            ->and(DB::connection()->getQueryLog())->toHaveCount(0);
    });

    it('updates the type', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['type' => 'boarding_house']);

        $this->actingAs($user)->put(route('properties.update', $property), [
            'name' => $property->name,
            'type' => 'hotel',
        ]);

        expect($property->refresh()->type)->toBe('hotel');
    });

    it('rejects a type that is not a known property type', function () {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [
                'name' => 'Mystery Manor',
                'type' => 'mansion',
            ])
            ->assertSessionHasErrors('type');
    });
});

describe('cross-property access', function () {
    it('denies admin from updating a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->put(route('properties.update', $propertyB), ['name' => 'Hacked Name'])
            ->assertForbidden();
    });

    it('denies admin from archiving a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->delete(route('properties.destroy', $propertyB))
            ->assertForbidden();
    });
});

describe('workspace tabs', function () {
    it('renders the leases tab with workspace stats', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.workspace.leases', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/leases')
                ->where('property.id', $property->id)
                ->where('property.type_label', 'Boarding House')
                ->where('property.city.id', $property->city_id)
                ->where('property.region.id', $property->region_id)
                ->has('property.units_count')
                ->has('property.occupied_units_count')
                ->has('property.tenants_count'));
    });

    it('renders the documents tab with workspace stats', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.workspace.documents', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/documents')
                ->where('property.id', $property->id)
                ->has('property.tenants_count'));
    });

    it('denies users without properties.view from workspace tabs', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.workspace.leases', $property))
            ->assertForbidden();
    });
});

describe('archive lifecycle', function () {
    it('blocks archiving a property with active leases', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        Lease::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($user)
            ->delete(route('properties.destroy', $property))
            ->assertRedirect();

        $property->refresh();

        expect($property->is_active)->toBeTrue();
    });

    it('restores an archived property', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->post(route('properties.restore', $property))
            ->assertRedirect();

        $property->refresh();

        expect($property->is_active)->toBeTrue();
    });
});
