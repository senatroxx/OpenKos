<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitType\UpdateUnitTypeRatesRequest;
use App\Models\Property;
use App\Models\UnitType;
use App\Services\UnitTypes\UpdateUnitTypeRates;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitTypeRateController extends Controller
{
    public function index(Property $property, UnitType $unitType): Response
    {
        $this->authorize('view', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        return Inertia::render('properties/unit-types/rates', [
            'property' => $property->load(['city', 'region', 'propertyType']),
            'unitType' => $unitType->load(['rates', 'activeRates']),
        ]);
    }

    public function update(
        UpdateUnitTypeRatesRequest $request,
        Property $property,
        UnitType $unitType,
        UpdateUnitTypeRates $updateUnitTypeRates,
    ): RedirectResponse {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $updateUnitTypeRates->execute(
            $unitType,
            $request->validated()['rates'],
            CarbonImmutable::parse($request->validated()['updated_at']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit Type pricing updated.')]);

        return back();
    }
}
