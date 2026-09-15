<?php

namespace App\Http\Controllers;

use App\Http\Requests\Property\UpdatePropertyRatesRequest;
use App\Models\Property;
use App\Services\Properties\UpdatePropertyRates;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyRateController extends Controller
{
    public function index(Property $property): Response
    {
        $this->authorize('view', $property);
        abort_unless($property->rental_mode->supportsPropertyPricing(), 404);

        $property->load(['city', 'region', 'propertyType']);

        $rates = $property->propertyRates()
            ->orderByRaw(
                "case billing_unit when 'day' then 1 when 'week' then 2 when 'month' then 3 when 'year' then 4 else 5 end"
            )
            ->orderBy('billing_interval')
            ->orderBy('id')
            ->get();

        return Inertia::render('properties/pricing', [
            'property' => $property,
            'rates' => $rates,
            'defaultRate' => $property->defaultActivePropertyRate(),
        ]);
    }

    public function update(
        UpdatePropertyRatesRequest $request,
        Property $property,
        UpdatePropertyRates $updatePropertyRates,
    ): RedirectResponse {
        $this->authorize('update', $property);

        try {
            $updatePropertyRates->execute(
                $property,
                $request->validated()['rates'],
                CarbonImmutable::parse($request->validated()['updated_at']),
            );
        } catch (QueryException $exception) {
            if (! $this->isPropertyRateUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'rates' => __('A rate with the same billing period and currency already exists.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property pricing updated.')]);

        return back();
    }

    private function isPropertyRateUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'property_rates_property_interval_unit_currency_unique')
            || (
                str_contains($message, 'property_rates')
                && str_contains($message, 'billing_interval')
                && str_contains($message, 'billing_unit')
                && str_contains($message, 'currency')
                && (str_contains($message, 'unique') || str_contains($message, 'duplicate'))
            );
    }
}
