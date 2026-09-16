<?php

namespace App\Http\Requests\Property;

use App\Enums\BillingUnit;
use App\Models\PropertyRate;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePropertyRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'updated_at' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*' => ['array'],
            'rates.*.id' => [
                'nullable',
                'integer',
                'distinct:strict',
                Rule::exists('property_rates', 'id')->where('property_id', $this->route('property')->id),
            ],
            'rates.*.billing_interval' => ['required', 'integer', 'min:1', 'max:255'],
            'rates.*.billing_unit' => ['required', 'string', Rule::in(BillingUnit::values())],
            'rates.*.currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(array_keys(app(MoneyConverter::class)->scales())),
            ],
            'rates.*.is_active' => ['nullable', 'boolean'],
            'rates.*.effective_from' => ['nullable', 'date'],
            'rates.*.effective_until' => ['nullable', 'date', 'after_or_equal:rates.*.effective_from'],
        ];

        $rates = $this->input('rates', []);
        if (! is_array($rates)) {
            return $rules;
        }

        foreach ($rates as $index => $rate) {
            if (! is_array($rate)) {
                continue;
            }

            $storedRate = isset($rate['id']) && is_scalar($rate['id'])
                ? PropertyRate::query()->where('property_id', $this->route('property')->id)->find($rate['id'])
                : null;
            $currency = $storedRate?->currency
                ?? (is_string($rate['currency'] ?? null) ? $rate['currency'] : null);
            $amountRules = ['required'];

            if ($currency !== null) {
                $amountRules[] = new MoneyAmount($currency);
            }

            $rules["rates.{$index}.amount"] = $amountRules;
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $property = $this->route('property');

            if (! $property->rental_mode->supportsPropertyPricing()) {
                $validator->errors()->add('rates', __('Entire property rates are only available for whole-property or hybrid properties.'));

                return;
            }

            $rates = $this->input('rates', []);
            if (! is_array($rates)) {
                return;
            }

            $submittedIds = collect($rates)
                ->filter(fn (mixed $rate): bool => is_array($rate))
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_scalar($id))
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $seen = [];
            PropertyRate::query()
                ->where('property_id', $property->id)
                ->when($submittedIds !== [], fn ($query) => $query->whereNotIn('id', $submittedIds))
                ->get()
                ->each(function (PropertyRate $rate) use (&$seen): void {
                    $seen[$this->rateKey($rate->billing_interval, $rate->billing_unit->value, $rate->currency)] = $rate->id;
                });

            foreach ($rates as $index => $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                $storedRate = isset($rate['id']) && is_scalar($rate['id'])
                    ? PropertyRate::query()->where('property_id', $property->id)->find($rate['id'])
                    : null;
                $currency = $storedRate?->currency;

                if ($storedRate === null) {
                    try {
                        $currency = app(MoneyConverter::class)->normalizeCurrency($rate['currency'] ?? null);
                    } catch (\Throwable) {
                        continue;
                    }

                    if (! app(InstallationCurrencySettings::class)->supports($currency)) {
                        $validator->errors()->add(
                            "rates.{$index}.currency",
                            __('This currency is not enabled for new pricing rates.'),
                        );
                    }
                }

                $key = $this->rateKey(
                    $rate['billing_interval'] ?? '',
                    $rate['billing_unit'] ?? '',
                    $currency ?? '',
                );
                $rateId = $storedRate?->id ?? -($index + 1);

                if (array_key_exists($key, $seen) && $seen[$key] !== $rateId) {
                    $validator->errors()->add(
                        "rates.{$index}.currency",
                        __('A property cannot have duplicate rates for the same billing period and currency.'),
                    );
                }

                $seen[$key] = $rateId;

                if ($storedRate && isset($rate['currency']) && $storedRate->currency !== $rate['currency']) {
                    $validator->errors()->add(
                        "rates.{$index}.currency",
                        __('An existing property-rate currency cannot be changed; add a new rate variant instead.'),
                    );
                }
            }
        }];
    }

    private function rateKey(mixed $interval, mixed $unit, mixed $currency): string
    {
        return implode('|', [$interval, $unit, strtoupper((string) $currency)]);
    }
}
