<?php

use App\Models\Unit;
use App\Models\UnitType;
use App\Services\Pricing\EffectiveUnitRateResolver;

it('overrides only the matching unit type rate identity', function (): void {
    $type = UnitType::factory()->create();
    $unit = Unit::factory()->for($type)->for($type->property)->create();
    $unit->rates()->update(['is_active' => false]);

    $type->rates()->createMany([
        ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => 100, 'currency' => 'IDR', 'is_active' => true],
        ['billing_interval' => 3, 'billing_unit' => 'month', 'amount' => 250, 'currency' => 'IDR', 'is_active' => true],
        ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => 10, 'currency' => 'USD', 'is_active' => true],
    ]);
    $override = $unit->rates()->firstOrFail();
    $override->update(['amount' => 125, 'is_active' => true]);

    $effective = app(EffectiveUnitRateResolver::class)->resolve($unit->load('unitType.activeRates', 'activeRates'));

    expect($effective)->toHaveCount(3)
        ->and($effective->pluck('rate.id')->all())->toContain($override->id)
        ->and($effective->where('source', 'unit_type'))->toHaveCount(2);
});

it('reveals the unit type rate when the matching override is inactive', function (): void {
    $type = UnitType::factory()->create();
    $unit = Unit::factory()->for($type)->for($type->property)->create();
    $unit->rates()->update(['is_active' => false]);
    $inherited = $type->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'month',
        'amount' => 100,
        'currency' => 'IDR',
        'is_active' => true,
    ]);
    $unit->rates()->firstOrFail()->update(['amount' => 125, 'is_active' => false]);

    $effective = app(EffectiveUnitRateResolver::class)->resolve($unit->load('unitType.activeRates', 'activeRates'));

    expect($effective)->toHaveCount(1)
        ->and($effective->first()['rate']->is($inherited))->toBeTrue()
        ->and($effective->first()['source'])->toBe('unit_type');
});
