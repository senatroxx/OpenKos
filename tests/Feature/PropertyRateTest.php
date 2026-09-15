<?php

use App\Enums\BillingUnit;
use App\Enums\PropertyRentalMode;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    Setting::set('supported_currencies', ['IDR', 'USD']);
    Setting::set('currency', 'IDR');
});

function propertyRatePayload(
    string $amount = '2500000',
    string $currency = 'IDR',
    int $interval = 1,
    string $unit = 'day',
    bool $active = true,
): array {
    return [
        'amount' => $amount,
        'currency' => $currency,
        'billing_interval' => $interval,
        'billing_unit' => $unit,
        'is_active' => $active,
    ];
}

describe('authorization', function (): void {
    it('redirects guests to login', function (): void {
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);

        $this->get(route('properties.pricing.index', $property))
            ->assertRedirect('login');
    });

    it('allows an owner to access property pricing', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.pricing.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('properties/pricing')
                ->has('rates', 0)
                ->where('defaultRate', null));
    });

    it('denies an assigned boundary from another property', function (): void {
        $admin = User::factory()->admin()->create();
        $assignedProperty = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $otherProperty = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $admin->properties()->sync([$assignedProperty->id]);

        $this->actingAs($admin)
            ->get(route('properties.pricing.index', $otherProperty))
            ->assertForbidden();
    });
});

describe('property pricing', function (): void {
    it('creates a whole-property rate without requiring a Unit', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload()],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rate = PropertyRate::firstOrFail();

        expect($rate->property_id)->toBe($property->id)
            ->and($rate->amount)->toBe('2500000.000')
            ->and($rate->currency)->toBe('IDR')
            ->and($rate->billing_unit)->toBe(BillingUnit::Day)
            ->and($property->units()->count())->toBe(0);
    });

    it('accepts whole-property rates for hybrid properties', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::Hybrid,
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload('12000000', 'IDR', 1, 'week')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($property->propertyRates()->count())->toBe(1);
    });

    it('rejects property pricing access for unit-mode properties', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::Unit,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.pricing.index', $property))
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload()],
            ])
            ->assertNotFound();

        expect(PropertyRate::query()->count())->toBe(0);
    });

    it('updates amounts and deactivates omitted rates without deleting history', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $keptRate = PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Day,
            'amount' => '2500000',
        ]);
        $omittedRate = PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Week,
            'amount' => '12000000',
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [array_merge(propertyRatePayload('3000000'), [
                    'id' => $keptRate->id,
                ])],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($keptRate->fresh()->amount)->toBe('3000000.000')
            ->and($keptRate->fresh()->is_active)->toBeTrue()
            ->and($omittedRate->fresh()->is_active)->toBeFalse()
            ->and(PropertyRate::query()->count())->toBe(2);
    });

    it('selects a preferred currency before billing-period order for the default rate', function (): void {
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Day,
            'amount' => '2500000',
            'currency' => 'USD',
        ]);
        $preferredRate = PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Month,
            'amount' => '35000000',
            'currency' => 'IDR',
        ]);

        expect($property->defaultActivePropertyRate()?->is($preferredRate))->toBeTrue();
    });

    it('rejects duplicate billing periods and currencies', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Day,
            'currency' => 'IDR',
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload()],
            ])
            ->assertSessionHasErrors('rates.0.currency');
    });

    it('allows one billing period per currency', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [
                    propertyRatePayload('2500000', 'IDR'),
                    propertyRatePayload('2500', 'USD'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($property->propertyRates()->count())->toBe(2);
    });

    it('validates money precision and supported currencies', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload('2500000.50')],
            ])
            ->assertSessionHasErrors('rates.0.amount');

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload('2500.123', 'USD')],
            ])
            ->assertSessionHasErrors('rates.0.amount');

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [propertyRatePayload('2500', 'EUR')],
            ])
            ->assertSessionHasErrors('rates.0.currency');

        expect(PropertyRate::query()->count())->toBe(0);
    });

    it('allows whole-property publication with an active property rate', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        PropertyRate::factory()->for($property)->create();

        $this->actingAs($owner)
            ->patch(route('properties.publication.update', $property), [
                'is_published' => true,
            ])
            ->assertRedirect();

        expect($property->refresh()->is_published)->toBeTrue();
    });

    it('protects rate identity and stale edits', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $rate = PropertyRate::factory()->for($property)->create([
            'billing_unit' => BillingUnit::Month,
        ]);

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $property->updated_at->toISOString(),
                'rates' => [array_merge(propertyRatePayload('3000000'), [
                    'id' => $rate->id,
                    'billing_unit' => 'year',
                ])],
            ])
            ->assertSessionHasErrors('rates.0.billing_unit');

        $staleUpdatedAt = $property->updated_at->copy()->subSecond()->toISOString();

        $this->actingAs($owner)
            ->put(route('properties.pricing.update', $property), [
                'updated_at' => $staleUpdatedAt,
                'rates' => [array_merge(propertyRatePayload('3000000', 'IDR', 1, 'month'), [
                    'id' => $rate->id,
                ])],
            ])
            ->assertSessionHasErrors('updated_at');

        expect($rate->fresh()->amount)->not->toBe('3000000.000');
    });

    it('retains property rates when rental mode changes to unit', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $rate = PropertyRate::factory()->for($property)->create();

        $this->actingAs($owner)
            ->put(route('properties.update', $property), [
                'name' => $property->name,
                'rental_mode' => PropertyRentalMode::Unit->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($property->refresh()->rental_mode)->toBe(PropertyRentalMode::Unit)
            ->and($rate->fresh()->exists)->toBeTrue();
    });
});
