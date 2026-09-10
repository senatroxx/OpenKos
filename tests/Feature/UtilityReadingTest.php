<?php

use App\Actions\Utility\RecordUtilityReading;
use App\Actions\Utility\UpdateUtilityReading;
use App\Models\Unit;
use App\Models\UtilityMeter;
use Illuminate\Validation\ValidationException;

it('calculates chained consumption and rejects duplicate or decreasing readings', function () {
    $unit = Unit::factory()->create();
    $meter = UtilityMeter::factory()->for($unit)->create();
    $action = app(RecordUtilityReading::class);

    $first = $action->execute($meter, [
        'reading_date' => '2026-01-31',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'previous_reading' => '25',
        'current_reading' => '100',
    ]);

    $second = $action->execute($meter, [
        'reading_date' => '2026-02-28',
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
        'previous_reading' => '100',
        'current_reading' => '145',
    ]);

    expect($first->consumption)->toBe('75.000')
        ->and($second->previous_reading_id)->toBe($first->id)
        ->and($second->consumption)->toBe('45.000');

    expect(fn () => $action->execute($meter, [
        'reading_date' => '2026-02-15',
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
        'previous_reading' => '100',
        'current_reading' => '120',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $action->execute($meter, [
        'reading_date' => '2026-03-31',
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'previous_reading' => '145',
        'current_reading' => '144',
    ]))->toThrow(ValidationException::class);
});

it('protects readings that later readings depend on', function () {
    $meter = UtilityMeter::factory()->create();
    $action = app(RecordUtilityReading::class);

    $first = $action->execute($meter, [
        'reading_date' => '2026-01-31',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'previous_reading' => '0',
        'current_reading' => '100',
    ]);
    $action->execute($meter, [
        'reading_date' => '2026-02-28',
        'period_start' => '2026-02-01',
        'period_end' => '2026-02-28',
        'previous_reading' => '100',
        'current_reading' => '150',
    ]);

    expect(fn () => app(UpdateUtilityReading::class)->execute($first, [
        'current_reading' => '110',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $first->delete())->toThrow(LogicException::class);
});
