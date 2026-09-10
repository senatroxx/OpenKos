<?php

namespace App\Http\Controllers;

use App\Actions\Utility\CreateUtilityReadingCorrection;
use App\Actions\Utility\RecordUtilityReading;
use App\Actions\Utility\UpdateUtilityReading;
use App\Http\Requests\Utility\StoreUtilityMeterRequest;
use App\Http\Requests\Utility\StoreUtilityReadingCorrectionRequest;
use App\Http\Requests\Utility\StoreUtilityReadingRequest;
use App\Http\Requests\Utility\UpdateUtilityMeterRequest;
use App\Http\Requests\Utility\UpdateUtilityReadingRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnitUtilityController extends Controller
{
    public function __construct(
        private RecordUtilityReading $recordReading,
        private UpdateUtilityReading $updateReading,
        private CreateUtilityReadingCorrection $createCorrection,
        private MoneyConverter $money,
    ) {}

    public function index(Property $property, Unit $unit): Response
    {
        $this->authorizeUnit($property, $unit, 'view');

        $unit->load([
            'property.city',
            'utilityMeters' => fn ($query) => $query
                ->orderBy('utility_type')
                ->orderBy('identifier')
                ->with([
                    'readings' => fn ($query) => $query
                        ->with(['invoiceLineItem.invoice', 'correctsReading.invoiceLineItem'])
                        ->withCount(['dependentReadings', 'corrections'])
                        ->latest('period_end')
                        ->latest('id'),
                ]),
        ]);

        return Inertia::render('properties/units/utilities', [
            'property' => $property,
            'unit' => $unit->only('id', 'slug', 'name', 'floor'),
            'meters' => $unit->utilityMeters->map(fn (UtilityMeter $meter): array => [
                'id' => $meter->id,
                'utility_type' => $meter->utility_type->value,
                'utility_name' => $meter->utility_name,
                'identifier' => $meter->identifier,
                'measurement_unit' => $meter->measurement_unit,
                'rate' => (string) $meter->rate,
                'currency' => $meter->currency,
                'is_active' => $meter->is_active,
                'readings' => $meter->readings->map(fn (UtilityReading $reading): array => $this->readingPayload($reading))->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function storeMeter(StoreUtilityMeterRequest $request, Property $property, Unit $unit): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $unit->utilityMeters()->create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility meter added.')]);

        return back();
    }

    public function updateMeter(UpdateUtilityMeterRequest $request, Property $property, Unit $unit, UtilityMeter $meter): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $this->assertMeterBelongsToUnit($meter, $unit);
        $meter->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility meter updated.')]);

        return back();
    }

    public function storeReading(StoreUtilityReadingRequest $request, Property $property, Unit $unit, UtilityMeter $meter): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $this->assertMeterBelongsToUnit($meter, $unit);
        $this->recordReading->execute($meter, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility reading recorded.')]);

        return back();
    }

    public function updateReading(UpdateUtilityReadingRequest $request, Property $property, Unit $unit, UtilityMeter $meter, UtilityReading $reading): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $this->assertReadingBelongsToMeter($meter, $reading);
        $this->updateReading->execute($reading, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility reading updated.')]);

        return back();
    }

    public function destroyReading(Property $property, Unit $unit, UtilityMeter $meter, UtilityReading $reading): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $this->assertReadingBelongsToMeter($meter, $reading);

        DB::transaction(function () use ($reading): void {
            $lockedReading = UtilityReading::query()->lockForUpdate()->findOrFail($reading->id);

            if ($lockedReading->isBilled()) {
                throw ValidationException::withMessages([
                    'reading' => __('Billed utility readings cannot be deleted.'),
                ]);
            }

            if ($lockedReading->hasDependents()) {
                throw ValidationException::withMessages([
                    'reading' => __('This reading is used by a later reading and cannot be deleted.'),
                ]);
            }

            $lockedReading->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility reading deleted.')]);

        return back();
    }

    public function storeCorrection(StoreUtilityReadingCorrectionRequest $request, Property $property, Unit $unit, UtilityMeter $meter, UtilityReading $reading): RedirectResponse
    {
        $this->authorizeUnit($property, $unit, 'update');
        $this->assertReadingBelongsToMeter($meter, $reading);
        $this->createCorrection->execute($reading, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utility correction recorded.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function readingPayload(UtilityReading $reading): array
    {
        $lineItem = $reading->invoiceLineItem;
        $charge = $reading->reading_kind->value === 'correction'
            ? BigDecimal::of((string) $reading->adjustment_consumption)
                ->multipliedBy((string) $reading->rate)
                ->toScale($this->money->scale($reading->currency), RoundingMode::HalfEven)
                ->toString()
            : $this->money->normalizeAmount(
                BigDecimal::of((string) $reading->consumption)->multipliedBy((string) $reading->rate)->toString(),
                $reading->currency,
            );

        return [
            'id' => $reading->id,
            'reading_kind' => $reading->reading_kind->value,
            'reading_date' => $reading->reading_date->toDateString(),
            'period_start' => $reading->period_start->toDateString(),
            'period_end' => $reading->period_end->toDateString(),
            'previous_reading' => (string) $reading->previous_reading,
            'current_reading' => (string) $reading->current_reading,
            'consumption' => (string) $reading->consumption,
            'adjustment_consumption' => $reading->adjustment_consumption === null ? null : (string) $reading->adjustment_consumption,
            'rate' => (string) $reading->rate,
            'currency' => $reading->currency,
            'reference' => $reading->reference,
            'corrects_reading_id' => $reading->corrects_reading_id,
            'charge_preview' => $charge,
            'billed' => $lineItem !== null,
            'invoice_reference' => $lineItem?->invoice?->reference,
            'can_edit' => $lineItem === null && $reading->dependent_readings_count === 0,
            'can_delete' => $lineItem === null && $reading->dependent_readings_count === 0,
            'correction_exists' => $reading->corrections_count > 0,
        ];
    }

    private function authorizeUnit(Property $property, Unit $unit, string $ability): void
    {
        abort_if($unit->property_id !== $property->id, 404);
        $this->authorize($ability, $unit);
    }

    private function assertMeterBelongsToUnit(UtilityMeter $meter, Unit $unit): void
    {
        abort_if($meter->unit_id !== $unit->id, 404);
    }

    private function assertReadingBelongsToMeter(UtilityMeter $meter, UtilityReading $reading): void
    {
        abort_if($reading->utility_meter_id !== $meter->id, 404);
    }
}
