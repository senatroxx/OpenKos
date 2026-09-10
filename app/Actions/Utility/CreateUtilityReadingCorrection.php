<?php

namespace App\Actions\Utility;

use App\Enums\UtilityReadingKind;
use App\Models\UtilityReading;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateUtilityReadingCorrection
{
    /**
     * Corrections store the corrected non-negative consumption plus a signed
     * delta from the billed reading for invoice adjustment purposes.
     *
     * @param  array{current_reading: string, reference?: string|null}  $data
     */
    public function execute(UtilityReading $reading, array $data): UtilityReading
    {
        try {
            return DB::transaction(function () use ($reading, $data): UtilityReading {
                $original = UtilityReading::query()->lockForUpdate()->findOrFail($reading->id);

                if ($original->reading_kind !== UtilityReadingKind::Reading || ! $original->isBilled()) {
                    throw ValidationException::withMessages([
                        'reading' => __('Only billed readings can be corrected.'),
                    ]);
                }

                if ($original->corrections()->exists()) {
                    throw ValidationException::withMessages([
                        'reading' => __('This reading already has a correction.'),
                    ]);
                }

                if (BigDecimal::of($data['current_reading'])->compareTo((string) $original->previous_reading) < 0) {
                    throw ValidationException::withMessages([
                        'current_reading' => __('The corrected reading cannot be lower than the previous effective reading.'),
                    ]);
                }

                $correctedConsumption = BigDecimal::of($data['current_reading'])
                    ->minus((string) $original->previous_reading)
                    ->toScale(3, RoundingMode::Unnecessary)
                    ->toString();
                $adjustmentConsumption = BigDecimal::of($correctedConsumption)
                    ->minus((string) $original->consumption)
                    ->toScale(3, RoundingMode::Unnecessary)
                    ->toString();

                return $original->meter()->firstOrFail()->readings()->create([
                    'reading_kind' => UtilityReadingKind::Correction,
                    'reading_date' => $original->reading_date,
                    'period_start' => $original->period_start,
                    'period_end' => $original->period_end,
                    'previous_reading_id' => null,
                    'previous_reading' => $original->previous_reading,
                    'current_reading' => $data['current_reading'],
                    'consumption' => $correctedConsumption,
                    'adjustment_consumption' => $adjustmentConsumption,
                    'rate' => $original->rate,
                    'currency' => $original->currency,
                    'reference' => $data['reference'] ?? null,
                    'corrects_reading_id' => $original->id,
                ]);
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'utility_readings_meter_period_kind_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'reading' => __('This reading already has a correction.'),
            ]);
        }
    }
}
