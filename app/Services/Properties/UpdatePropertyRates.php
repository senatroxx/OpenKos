<?php

namespace App\Services\Properties;

use App\Models\Property;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UpdatePropertyRates
{
    public function __construct(
        private InstallationCurrencySettings $currencies,
        private MoneyConverter $money,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    public function execute(Property $property, array $rates, CarbonImmutable $expectedUpdatedAt): Property
    {
        return DB::transaction(function () use ($property, $rates, $expectedUpdatedAt): Property {
            $lockedProperty = Property::query()->lockForUpdate()->findOrFail($property->id);

            if (! $lockedProperty->updated_at?->equalTo($expectedUpdatedAt)) {
                throw ValidationException::withMessages([
                    'updated_at' => __('This property changed while you were editing it. Refresh and try again.'),
                ]);
            }

            abort_if(
                ! $lockedProperty->rental_mode->supportsPropertyPricing(),
                422,
                __('Entire property rates are only available for whole-property or hybrid properties.'),
            );

            $rates = $this->normalizeRates($lockedProperty, $rates);
            $submittedIds = collect($rates)
                ->pluck('id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($submittedIds === []) {
                $lockedProperty->propertyRates()->where('is_active', true)->update(['is_active' => false]);
            } else {
                $lockedProperty->propertyRates()
                    ->where('is_active', true)
                    ->whereNotIn('id', $submittedIds)
                    ->update(['is_active' => false]);
            }

            foreach ($rates as $rate) {
                if (isset($rate['id'])) {
                    $propertyRate = $lockedProperty->propertyRates()->whereKey($rate['id'])->firstOrFail();
                    $propertyRate->update([
                        'amount' => $rate['amount'],
                        'is_active' => $rate['is_active'] ?? true,
                    ]);

                    continue;
                }

                $lockedProperty->propertyRates()->create($rate);
            }

            $lockedProperty->touch();

            return $lockedProperty->fresh(['propertyRates']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRates(Property $property, array $rates): array
    {
        $hasNewRate = collect($rates)->contains(fn (array $rate): bool => ! isset($rate['id']));

        if ($hasNewRate) {
            $this->currencies->lockForUpdate();
        }

        $defaultCurrency = $this->currencies->default(fresh: true);
        $supportedCurrencies = $this->currencies->freshSupported();

        return collect($rates)->map(function (array $rate, int $index) use ($property, $defaultCurrency, $supportedCurrencies): array {
            $propertyRate = isset($rate['id'])
                ? $property->propertyRates()->whereKey($rate['id'])->firstOrFail()
                : null;
            $currency = $propertyRate?->currency ?? ($rate['currency'] ?? $defaultCurrency);

            if ($propertyRate !== null) {
                if (isset($rate['billing_interval']) && (int) $rate['billing_interval'] !== $propertyRate->billing_interval) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.billing_interval" => __('An existing property-rate billing interval cannot be changed; add a new rate variant instead.'),
                    ]);
                }

                if (isset($rate['billing_unit']) && $rate['billing_unit'] !== $propertyRate->billing_unit->value) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.billing_unit" => __('An existing property-rate billing unit cannot be changed; add a new rate variant instead.'),
                    ]);
                }

                if (isset($rate['currency']) && strtoupper((string) $rate['currency']) !== $propertyRate->currency) {
                    throw ValidationException::withMessages([
                        "rates.{$index}.currency" => __('An existing property-rate currency cannot be changed; add a new rate variant instead.'),
                    ]);
                }
            } elseif (! in_array($this->money->normalizeCurrency($currency), $supportedCurrencies, true)) {
                throw ValidationException::withMessages([
                    "rates.{$index}.currency" => __('This currency is not enabled for new pricing rates.'),
                ]);
            }

            try {
                $currency = $this->money->normalizeCurrency($currency);
                $rate['amount'] = $this->money->normalizeAmount((string) $rate['amount'], $currency);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    "rates.{$index}.amount" => __('The amount is invalid for the selected currency.'),
                ]);
            }

            $rate['currency'] = $currency;

            return $rate;
        })->values()->all();
    }
}
