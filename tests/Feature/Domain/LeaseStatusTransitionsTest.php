<?php

use App\Business\Leases\LeaseStatusValidator;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

it('validates active to terminated transition', function () {
    $validator = app(LeaseStatusValidator::class);

    expect(fn () => $validator->validate(LeaseStatus::Active, LeaseStatus::Terminated))->not->toThrow(Exception::class);
});

it('validates active to renewed transition', function () {
    $validator = app(LeaseStatusValidator::class);

    expect(fn () => $validator->validate(LeaseStatus::Active, LeaseStatus::Renewed))->not->toThrow(Exception::class);
});

it('rejects terminated to active transition', function () {
    $validator = app(LeaseStatusValidator::class);

    expect(fn () => $validator->validate(LeaseStatus::Terminated, LeaseStatus::Active))->toThrow(Exception::class);
});

it('rejects terminated to renewed transition', function () {
    $validator = app(LeaseStatusValidator::class);

    expect(fn () => $validator->validate(LeaseStatus::Terminated, LeaseStatus::Renewed))->toThrow(Exception::class);
});

it('rejects renewed to any transition', function () {
    $validator = app(LeaseStatusValidator::class);

    expect(fn () => $validator->validate(LeaseStatus::Renewed, LeaseStatus::Active))->toThrow(Exception::class);
    expect(fn () => $validator->validate(LeaseStatus::Renewed, LeaseStatus::Terminated))->toThrow(Exception::class);
});

it('transitions lease from active to terminated via controller', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1_000_000)->create(['property_id' => $property->id]);
    $user = User::factory()->owner()->create();
    $tenant = Tenant::factory()->create();

    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
    ]);

    $this->actingAs($user)
        ->delete(route('properties.units.leases.destroy', [$property, $unit, $lease]), ['reason' => 'test']);

    expect($lease->fresh()->status)->toBe(LeaseStatus::Terminated);
});

it('transitions lease from active to renewed via renewal', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->withRate(1_000_000)->create(['property_id' => $property->id]);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->create();

    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => LeaseStatus::Active,
        'start_date' => now()->subMonths(6),
        'end_date' => now()->addMonths(6),
        'rent_amount' => 1_000_000,
        'deposit_amount' => 500_000,
        'deposit_paid_at' => now(),
        'rent_due_day' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('leases.renew', $lease), [
            'rent_amount' => 1_200_000,
            'extension_value' => 12,
            'extension_unit' => 'months',
            'deposit_handling' => 'carry_forward',
            'confirmed_outstanding' => true,
        ]);

    expect($lease->fresh()->status)->toBe(LeaseStatus::Renewed);
});
