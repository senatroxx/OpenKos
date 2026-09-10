<?php

namespace App\Actions\Utility;

use App\Enums\UtilityReadingKind;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordUtilityReading
{
    /**
     * @param  array{reading_date: string, period_start: string, period_end: string, previous_reading: string, current_reading: string, reference?: string|null}  $data
     */
    public function execute(UtilityMeter $meter, array $data): UtilityReading
    {
        try {
            return DB::transaction(function () use ($meter, $data): UtilityReading {
                $lockedMeter = UtilityMeter::query()->lockForUpdate()->findOrFail($meter->id);
                $periodStart = CarbonImmutable::parse($data['period_start'])->startOfDay();
                $periodEnd = CarbonImmutable::parse($data['period_end'])->startOfDay();
                $readingDate = CarbonImmutable::parse($data['reading_date'])->startOfDay();

                if ($periodEnd->lt($periodStart) || $readingDate->lt($periodStart) || $readingDate->gt($periodEnd)) {
                    throw ValidationException::withMessages([
                        'period_start' => __('The reading date must fall within its utility period.'),
                    ]);
                }

                $overlapping = $lockedMeter->readings()
                    ->where('reading_kind', UtilityReadingKind::Reading->value)
                    ->whereDate('period_start', '<=', $periodEnd)
                    ->whereDate('period_end', '>=', $periodStart)
                    ->exists();

                if ($overlapping) {
                    throw ValidationException::withMessages([
                        'period_start' => __('This meter already has a reading covering that utility period.'),
                    ]);
                }

                $previous = $lockedMeter->readings()
                    ->where('reading_kind', UtilityReadingKind::Reading->value)
                    ->whereDate('period_end', '<', $periodStart)
                    ->latest('period_end')
                    ->latest('id')
                    ->first();
                $previousValue = (string) ($previous?->current_reading ?? $data['previous_reading']);

                if ($previous !== null && $this->compare($data['previous_reading'], $previousValue) !== 0) {
                    throw ValidationException::withMessages([
                        'previous_reading' => __('The previous reading must match the meter’s latest effective reading.'),
                    ]);
                }

                if ($previous !== null && CarbonImmutable::instance($previous->period_end)->addDay()->gt($periodStart)) {
                    throw ValidationException::withMessages([
                        'period_start' => __('The utility period overlaps the previous reading.'),
                    ]);
                }

                if ($this->compare($data['current_reading'], $previousValue) < 0) {
                    throw ValidationException::withMessages([
                        'current_reading' => __('The current reading cannot be lower than the previous effective reading.'),
                    ]);
                }

                $consumption = BigDecimal::of($data['current_reading'])
                    ->minus($previousValue)
                    ->toScale(3, RoundingMode::Unnecessary)
                    ->toString();

                return $lockedMeter->readings()->create([
                    'reading_kind' => UtilityReadingKind::Reading,
                    'reading_date' => $readingDate,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'previous_reading_id' => $previous?->id,
                    'previous_reading' => $previousValue,
                    'current_reading' => $data['current_reading'],
                    'consumption' => $consumption,
                    'adjustment_consumption' => null,
                    'rate' => $lockedMeter->rate,
                    'currency' => $lockedMeter->currency,
                    'reference' => $data['reference'] ?? null,
                ]);
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'utility_readings_meter_period_kind_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'period_start' => __('This meter already has a reading for that utility period.'),
            ]);
        }
    }

    private function compare(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo(BigDecimal::of($right));
    }
}
