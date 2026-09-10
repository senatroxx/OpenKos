<?php

use App\Actions\Utility\RecordUtilityReading;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Setting::set('supported_currencies', ['IDR', 'USD']);
});

it('provides a utilities workspace and manages unit meters', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();

    $this->actingAs($user)
        ->get(route('properties.units.utilities', [$property, $unit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('properties/units/utilities')
            ->has('meters', 0)
        );

    $this->actingAs($user)
        ->post(route('properties.units.utilities.meters.store', [$property, $unit]), [
            'utility_type' => 'electricity',
            'identifier' => 'ELEC-001',
            'measurement_unit' => 'kWh',
            'rate' => '1000',
            'currency' => 'IDR',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $meter = UtilityMeter::query()->where('identifier', 'ELEC-001')->firstOrFail();

    expect($meter->unit_id)->toBe($unit->id)
        ->and($meter->rate)->toBe('1000.000')
        ->and($meter->currency)->toBe('IDR');

    $this->actingAs($user)
        ->put(route('properties.units.utilities.meters.update', [$property, $unit, $meter]), [
            'utility_type' => 'electricity',
            'identifier' => 'ELEC-001',
            'measurement_unit' => 'kWh',
            'rate' => '1250',
            'currency' => 'IDR',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($meter->refresh()->rate)->toBe('1250.000');
});

it('snapshots meter rate and currency on each reading', function () {
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $meter = UtilityMeter::factory()->for($unit)->create([
        'rate' => '1000',
        'currency' => 'IDR',
    ]);

    $first = app(RecordUtilityReading::class)->execute($meter, [
        'reading_date' => '2026-01-31',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'previous_reading' => '0',
        'current_reading' => '10',
    ]);

    $meter->update([
        'utility_type' => $meter->utility_type->value,
        'identifier' => $meter->identifier,
        'measurement_unit' => $meter->measurement_unit,
        'rate' => '1500',
        'currency' => 'IDR',
        'is_active' => true,
    ]);

    $second = app(RecordUtilityReading::class)->execute($meter->refresh(), [
        'reading_date' => '2026-02-28',
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
        'previous_reading' => '10',
        'current_reading' => '20',
    ]);

    expect($first->rate)->toBe('1000.000')
        ->and($first->currency)->toBe('IDR')
        ->and($second->rate)->toBe('1500.000')
        ->and($second->currency)->toBe('IDR')
        ->and(UtilityReading::query()->count())->toBe(2);
});

it('stores custom utility names and clears them for standard meters', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();

    $this->actingAs($user)
        ->post(route('properties.units.utilities.meters.store', [$property, $unit]), [
            'utility_type' => 'custom',
            'utility_name' => 'Gas',
            'identifier' => 'GAS-001',
            'measurement_unit' => 'kg',
            'rate' => '20000',
            'currency' => 'IDR',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $meter = UtilityMeter::query()->where('identifier', 'GAS-001')->firstOrFail();

    expect($meter->utility_name)->toBe('Gas');

    $this->actingAs($user)
        ->put(route('properties.units.utilities.meters.update', [$property, $unit, $meter]), [
            'utility_type' => 'water',
            'utility_name' => 'Gas',
            'identifier' => 'GAS-001',
            'measurement_unit' => 'm³',
            'rate' => '7500',
            'currency' => 'IDR',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($meter->refresh()->utility_name)->toBeNull();
});
