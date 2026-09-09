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

class UpdateUtilityReading
{
    /**
     * @param  array<string, string|null>  $data
     */
    public function execute(UtilityReading $reading, array $data): UtilityReading
    {
        try {
            return DB::transaction(function () use ($reading, $data): UtilityReading {
                $lockedReading = UtilityReading::query()->findOrFail($reading->id);
                $meter = UtilityMeter::query()->lockForUpdate()->findOrFail($lockedReading->utility_meter_id);
                $lockedReading->setRelation('meter', $meter);

                if ($lockedReading->isBilled()) {
                    throw ValidationException::withMessages([
                        'reading' => __('Billed utility readings cannot be changed.'),
                    ]);
                }

                if ($lockedReading->hasDependents()) {
                    throw ValidationException::withMessages([
                        'reading' => __('This reading is used by a later reading and cannot be changed.'),
                    ]);
                }

                if ($lockedReading->reading_kind === UtilityReadingKind::Correction) {
                    return $this->updateCorrection($lockedReading, $data);
                }

                return $this->updateReading($lockedReading, $data);
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

    /**
     * @param  array<string, string|null>  $data
     */
    private function updateReading(UtilityReading $reading, array $data): UtilityReading
    {
        $periodStart = CarbonImmutable::parse($data['period_start'] ?? $reading->period_start)->startOfDay();
        $periodEnd = CarbonImmutable::parse($data['period_end'] ?? $reading->period_end)->startOfDay();
        $readingDate = CarbonImmutable::parse($data['reading_date'] ?? $reading->reading_date)->startOfDay();

        if ($periodEnd->lt($periodStart) || $readingDate->lt($periodStart) || $readingDate->gt($periodEnd)) {
            throw ValidationException::withMessages([
                'period_start' => __('The reading date must fall within its utility period.'),
            ]);
        }

        $overlapping = $reading->meter->readings()
            ->where($reading->getQualifiedKeyName(), '!=', $reading->id)
            ->where('reading_kind', UtilityReadingKind::Reading->value)
            ->whereDate('period_start', '<=', $periodEnd)
            ->whereDate('period_end', '>=', $periodStart)
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'period_start' => __('This meter already has a reading covering that utility period.'),
            ]);
        }

        $previous = $reading->meter->readings()
            ->where($reading->getQualifiedKeyName(), '!=', $reading->id)
            ->where('reading_kind', UtilityReadingKind::Reading->value)
            ->whereDate('period_end', '<', $periodStart)
            ->latest('period_end')
            ->latest('id')
            ->first();
        $previousValue = (string) ($previous?->current_reading ?? $data['previous_reading'] ?? $reading->previous_reading);

        if ($previous !== null && isset($data['previous_reading']) && $this->compare($data['previous_reading'], $previousValue) !== 0) {
            throw ValidationException::withMessages([
                'previous_reading' => __('The previous reading must match the meter’s latest effective reading.'),
            ]);
        }

        if ($this->compare((string) $data['current_reading'], $previousValue) < 0) {
            throw ValidationException::withMessages([
                'current_reading' => __('The current reading cannot be lower than the previous effective reading.'),
            ]);
        }

        $consumption = BigDecimal::of((string) $data['current_reading'])
            ->minus($previousValue)
            ->toScale(3, RoundingMode::Unnecessary)
            ->toString();

        $reading->update([
            'reading_date' => $readingDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'previous_reading_id' => $previous?->id,
            'previous_reading' => $previousValue,
            'current_reading' => $data['current_reading'],
            'consumption' => $consumption,
            'adjustment_consumption' => null,
            'reference' => $data['reference'] ?? null,
        ]);

        return $reading->refresh();
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function updateCorrection(UtilityReading $reading, array $data): UtilityReading
    {
        $original = $reading->correctsReading;

        if ($original === null) {
            throw ValidationException::withMessages([
                'reading' => __('This correction is missing its original reading.'),
            ]);
        }

        if ($this->compare((string) $data['current_reading'], (string) $original->previous_reading) < 0) {
            throw ValidationException::withMessages([
                'current_reading' => __('The corrected reading cannot be lower than the previous effective reading.'),
            ]);
        }

        $consumption = BigDecimal::of((string) $data['current_reading'])
            ->minus((string) $original->previous_reading)
            ->toScale(3, RoundingMode::Unnecessary)
            ->toString();
        $adjustmentConsumption = BigDecimal::of($consumption)
            ->minus((string) $original->consumption)
            ->toScale(3, RoundingMode::Unnecessary)
            ->toString();

        $reading->update([
            'current_reading' => $data['current_reading'],
            'consumption' => $consumption,
            'adjustment_consumption' => $adjustmentConsumption,
            'reference' => $data['reference'] ?? null,
        ]);

        return $reading->refresh();
    }

    private function compare(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo(BigDecimal::of($right));
    }
}
