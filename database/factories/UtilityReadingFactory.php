<?php

namespace Database\Factories;

use App\Enums\UtilityReadingKind;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityReading>
 */
class UtilityReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = CarbonImmutable::now()->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        return [
            'utility_meter_id' => UtilityMeter::factory(),
            'reading_kind' => UtilityReadingKind::Reading,
            'reading_date' => $periodEnd,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'previous_reading_id' => null,
            'previous_reading' => '0',
            'current_reading' => '100',
            'consumption' => '100',
            'adjustment_consumption' => null,
            'rate' => '1000',
            'currency' => 'IDR',
            'reference' => null,
            'corrects_reading_id' => null,
        ];
    }
}
