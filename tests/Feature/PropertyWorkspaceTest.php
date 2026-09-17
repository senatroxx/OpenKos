<?php

use App\Enums\PropertyRentalMode;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('rental-mode workspace access', function (): void {
    it('includes whole-property tenants in staff tenant and property views', function (): void {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::Hybrid,
        ]);
        $admin->properties()->sync([$property->id]);
        $tenant = Tenant::factory()->create(['name' => 'Whole Property Tenant']);

        Lease::factory()->wholeProperty($property)->create([
            'primary_tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin)
            ->get(route('properties.units.index', $property))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'tenants',
                fn ($tenants) => collect($tenants)->pluck('id')->contains($tenant->id),
            ));

        $this->actingAs($admin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'tenants.data',
                fn ($tenants) => collect($tenants)->pluck('id')->contains($tenant->id),
            ));
    });

    it('hides all unit inventory routes for whole-property properties', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $unit = Unit::factory()->for($property)->create();
        $unitType = UnitType::factory()->for($property)->create();

        $this->actingAs($owner)
            ->get(route('properties.units.index', $property))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('properties.units.show', [$property, $unit]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('properties.unit-types.index', $property))
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('properties.unit-types.update', [$property, $unitType]), [
                'name' => 'Updated type',
            ])
            ->assertNotFound();
    });

    it('preserves property access boundaries before applying mode restrictions', function (): void {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::WholeProperty,
        ]);
        $admin->properties()->sync([]);

        $this->actingAs($admin)
            ->get(route('properties.units.index', $property))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('properties.unit-types.index', $property))
            ->assertForbidden();
    });

    it('keeps both workspaces available for hybrid properties', function (): void {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create([
            'rental_mode' => PropertyRentalMode::Hybrid,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.units.index', $property))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('properties.unit-types.index', $property))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('properties.pricing.index', $property))
            ->assertOk();
    });
});

it('retains dormant inventory, rates, leases, invoices, and property rates across mode changes', function (): void {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $unitType = UnitType::factory()->for($property)->create();
    $unit = Unit::factory()->for($property)->create(['unit_type_id' => $unitType->id]);
    $unitRate = $unit->rates()->firstOrFail();
    $propertyRate = PropertyRate::factory()->for($property)->create();
    $lease = Lease::factory()->for($unit)->terminated()->create();
    $invoice = Invoice::factory()->for($lease)->create();

    $this->actingAs($owner)
        ->put(route('properties.update', $property), [
            'name' => $property->name,
            'rental_mode' => PropertyRentalMode::WholeProperty->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('properties.units.index', $property))
        ->assertNotFound();
    $this->actingAs($owner)
        ->get(route('properties.pricing.index', $property))
        ->assertOk();

    $this->actingAs($owner)
        ->put(route('properties.update', $property), [
            'name' => $property->name,
            'rental_mode' => PropertyRentalMode::Hybrid->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('properties.units.index', $property))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('properties.unit-types.index', $property))
        ->assertOk();

    $this->actingAs($owner)
        ->put(route('properties.update', $property), [
            'name' => $property->name,
            'rental_mode' => PropertyRentalMode::Unit->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('properties.units.index', $property))
        ->assertOk();
    $this->actingAs($owner)
        ->get(route('properties.pricing.index', $property))
        ->assertNotFound();

    expect($property->refresh()->rental_mode)->toBe(PropertyRentalMode::Unit)
        ->and($property->units()->count())->toBe(1)
        ->and($property->unitTypes()->count())->toBe(1)
        ->and($unitRate->fresh()->exists)->toBeTrue()
        ->and($propertyRate->fresh()->exists)->toBeTrue()
        ->and($lease->fresh()->unit_id)->toBe($unit->id)
        ->and($invoice->fresh()->lease_id)->toBe($lease->id);
});

it('keeps historical unit leases readable after switching to whole-property mode', function (): void {
    $owner = User::factory()->owner()->create();
    $property = Property::factory()->create([
        'rental_mode' => PropertyRentalMode::Unit,
    ]);
    $unit = Unit::factory()->for($property)->create();
    $lease = Lease::factory()->for($unit)->terminated()->create();

    $this->actingAs($owner)
        ->put(route('properties.update', $property), [
            'name' => $property->name,
            'rental_mode' => PropertyRentalMode::WholeProperty->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('properties.workspace.leases', $property))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data.0.id', $lease->id)
            ->where('leases.data.0.unit_id', $unit->id));
});
