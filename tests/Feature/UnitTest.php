<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('authorization', function () {
    it('redirects unauthenticated users to login', function () {
        $property = Property::factory()->create();

        $this->get(route('properties.units.index', $property))->assertRedirect('login');
    });

    it('returns 403 for users without units.view permission', function () {
        $user = User::factory()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]))
            ->assertForbidden();
    });
});

describe('archive lifecycle', function () {
    it('restores a soft-deleted unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $unit->delete();

        expect(Unit::count())->toBe(0);

        $this->actingAs($user)
            ->post(route('properties.units.restore', [$property, $unit]))
            ->assertRedirect();

        expect(Unit::count())->toBe(1);
    });

    it('denies admin restoring a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();
        $unit->delete();

        $this->actingAs($admin)
            ->post(route('properties.units.restore', [$propertyB, $unit]))
            ->assertForbidden();
    });

    it('blocks deleting a unit with active leases', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        Lease::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]))
            ->assertRedirect();
    });
});

describe('authorization', function () {
    it('allows admin to access units', function () {
        $user = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $user->properties()->sync([$property->id]);

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk();
    });

    it('allows owner to access units', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk();
    });
});

describe('CRUD', function () {
    it('lists units on the index page', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->count(3)->for($property)->create();

        $this->actingAs($user)
            ->get(route('properties.units.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/units/index')
                ->has('units.data', 3)
                ->has('availableUnits.0.active_rates.0.amount')
            );
    });

    it('creates a unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($user)->post(route('properties.units.store', $property), [
            'name' => 'Unit 101',
            'capacity' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $unit = Unit::first();

        expect($unit)->not->toBeNull();
        expect($unit->name)->toBe('Unit 101');
        expect($unit->property_id)->toBe($property->id);
    });

    it('validates required fields on create', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.store', $property), [])
            ->assertSessionHasErrors(['name', 'capacity']);
    });

    it('updates a unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create(['name' => 'Unit 101']);

        $this->actingAs($user)
            ->put(route('properties.units.update', [$property, $unit]), [
                'name' => 'Unit 102',
                'capacity' => 2,
            ]);

        $unit->refresh();

        expect($unit->name)->toBe('Unit 102');
    });

    it('preserves inactive rates when updating a unit', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $activeRate = $unit->activeRates()->firstOrFail();
        $inactiveRate = $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'year',
            'amount' => '12000.00',
            'currency' => 'USD',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->put(route('properties.units.update', [$property, $unit]), [
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'rates' => [[
                    'id' => $activeRate->id,
                    'billing_interval' => $activeRate->billing_interval,
                    'billing_unit' => $activeRate->billing_unit->value,
                    'amount' => $activeRate->amount,
                    'currency' => $activeRate->currency,
                    'is_active' => true,
                ]],
            ])
            ->assertRedirect();

        expect($inactiveRate->fresh())->not->toBeNull();
    });

    it('deletes a unit via soft delete', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $this->actingAs($user)
            ->delete(route('properties.units.destroy', [$property, $unit]));

        expect(Unit::count())->toBe(0);
        expect(Unit::withTrashed()->count())->toBe(1);
    });

    it('filters units by status', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->for($property)->count(2)->create();
        Unit::factory()->for($property)->occupied()->create();
        Unit::factory()->for($property)->maintenance()->create();

        $response = $this->actingAs($user)
            ->get(route('properties.units.index', [$property, 'status' => 'occupied']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->has('units.data', 1)
            ->where('status', 'occupied')
        );
    });

    it('filters archived units', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->for($property)->create();
        $archived = Unit::factory()->for($property)->create();
        $archived->delete();

        $response = $this->actingAs($user)
            ->get(route('properties.units.index', [$property, 'status' => 'archived']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->has('units.data', 1)
        );
    });

    it('searches units by name', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();
        Unit::factory()->for($property)->create(['name' => 'Deluxe Suite']);
        Unit::factory()->for($property)->create(['name' => 'Standard Unit']);

        $response = $this->actingAs($user)
            ->get(route('properties.units.index', [$property, 'search' => 'Deluxe']))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('properties/units/index')
            ->has('units.data', 1)
        );
    });
});

describe('cross-property access', function () {
    it('denies admin viewing units of a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->get(route('properties.units.index', $propertyB))
            ->assertForbidden();
    });

    it('denies admin creating a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);

        $this->actingAs($admin)
            ->post(route('properties.units.store', $propertyB), [
                'name' => 'Unit 101',
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin updating a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->put(route('properties.units.update', [$propertyB, $unit]), [
                'name' => 'Hacked',
                'capacity' => 1,
            ])
            ->assertForbidden();
    });

    it('denies admin deleting a unit in a property they are not assigned to', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $unit = Unit::factory()->for($propertyB)->create();

        $this->actingAs($admin)
            ->delete(route('properties.units.destroy', [$propertyB, $unit]))
            ->assertForbidden();
    });
});

describe('slug', function () {
    it('auto-generates a slug from the name', function () {
        $unit = Unit::factory()->create(['name' => 'Unit A1']);

        expect($unit->slug)->toBe('unit-a1');
    });

    it('allows the same slug across different properties', function () {
        $a = Unit::factory()->for(Property::factory())->create(['name' => 'A1']);
        $b = Unit::factory()->for(Property::factory())->create(['name' => 'A1']);

        expect($a->slug)->toBe('a1')->and($b->slug)->toBe('a1');
    });

    it('disambiguates a slug collision within a property', function () {
        $property = Property::factory()->create();

        // Distinct names (property_id + name is unique) that slugify identically.
        $first = Unit::factory()->for($property)->create(['name' => 'A1']);
        $second = Unit::factory()->for($property)->create(['name' => 'A1.']);

        expect($first->slug)->toBe('a1')->and($second->slug)->toBe('a1-2');
    });
});

describe('active rates ordering', function () {
    it('surfaces the shortest billing period first, regardless of insertion order', function () {
        $unit = Unit::factory()->create();
        // Drop the factory's default month rate for a clean slate.
        $unit->rates()->delete();

        // Insert the yearly rate first (lower id) to prove ordering is by
        // period length — not insertion order, and not the tied billing_interval.
        $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'year',
            'amount' => 15_000_000,
            'is_active' => true,
        ]);
        $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => 1_500_000,
            'is_active' => true,
        ]);

        $first = $unit->activeRates()->first();

        expect($first->billing_unit->value)->toBe('month')
            ->and($first->amount)->toBe('1500000.000');
    });
});

describe('currency-specific rates', function () {
    it('allows independent prices for the same billing period in different currencies', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.store', $property), [
                'name' => 'Unit 101',
                'capacity' => 1,
                'rates' => [
                    [
                        'billing_interval' => 1,
                        'billing_unit' => 'month',
                        'amount' => '1500000',
                        'currency' => 'IDR',
                    ],
                    [
                        'billing_interval' => 1,
                        'billing_unit' => 'month',
                        'amount' => '95.00',
                        'currency' => 'USD',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit = Unit::query()->where('name', 'Unit 101')->firstOrFail();

        expect($unit->rates()->orderBy('currency')->pluck('currency')->all())
            ->toBe(['IDR', 'USD'])
            ->and($unit->rates()->where('currency', 'USD')->value('amount'))
            ->toBe('95.000');
    });

    it('rejects duplicate currency variants in one request', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.store', $property), [
                'name' => 'Unit 101',
                'capacity' => 1,
                'rates' => [
                    [
                        'billing_interval' => 1,
                        'billing_unit' => 'month',
                        'amount' => '1500000',
                        'currency' => 'IDR',
                    ],
                    [
                        'billing_interval' => 1,
                        'billing_unit' => 'month',
                        'amount' => '1600000',
                        'currency' => 'IDR',
                    ],
                ],
            ])
            ->assertSessionHasErrors('rates.1.currency');
    });

    it('rejects malformed rate rows without throwing', function () {
        $user = User::factory()->owner()->create();
        $property = Property::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.units.store', $property), [
                'name' => 'Unit 101',
                'capacity' => 1,
                'rates' => ['invalid'],
            ])
            ->assertSessionHasErrors('rates.0');
    });

    it('enforces currency variant uniqueness at the database level', function () {
        $unit = Unit::factory()->create();

        $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => '95.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        expect(fn () => $unit->rates()->create([
            'billing_interval' => 1,
            'billing_unit' => 'month',
            'amount' => '100.00',
            'currency' => 'USD',
            'is_active' => true,
        ]))->toThrow(QueryException::class);
    });

    it('rejects duplicate effective currencies for legacy null rates', function () {
        Setting::set('currency', 'IDR');
        $unit = Unit::factory()->create();
        $rate = $unit->rates()->firstOrFail();

        DB::table('unit_rates')->where('id', $rate->id)->update(['currency' => null]);

        $user = User::factory()->owner()->create();
        $response = $this->actingAs($user)
            ->put(route('properties.units.update', [$unit->property, $unit]), [
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'rates' => [
                    [
                        'id' => $rate->id,
                        'billing_interval' => $rate->billing_interval,
                        'billing_unit' => $rate->billing_unit->value,
                        'amount' => $rate->amount,
                        'currency' => 'IDR',
                        'is_active' => true,
                    ],
                    [
                        'billing_interval' => $rate->billing_interval,
                        'billing_unit' => $rate->billing_unit->value,
                        'amount' => '1000000',
                        'currency' => 'IDR',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('rates.1.currency');
    });

    it('does not reinterpret an existing rate when the default currency changes', function () {
        Setting::set('currency', 'IDR');
        $unit = Unit::factory()->withRate(1_500_000)->create();
        $rate = $unit->rates()->firstOrFail();

        Setting::set('currency', 'USD');

        expect($rate->fresh()->currency)->toBe('IDR');
    });
});
