<?php

namespace App\Http\Requests\Unit;

use App\Enums\BillingUnit;
use App\Enums\UnitStatus;
use App\Models\UnitRate;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateUnitRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units')
                    ->ignore($this->route('unit')->id)
                    ->where(fn ($q) => $q->where('property_id', $this->route('property')->id)),
            ],
            'floor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:0', 'max:255'],
            'status' => ['nullable', new Enum(UnitStatus::class)],
            'notes' => ['nullable', 'string', 'max:65535'],
            'updated_at' => ['required', 'date'],
            'rates' => ['sometimes', 'array'],
            'rates.*' => ['array'],
            'rates.*.id' => [
                'nullable',
                'integer',
                'distinct:strict',
                Rule::exists('unit_rates', 'id')->where('unit_id', $this->route('unit')->id),
            ],
            'rates.*.billing_interval' => ['required_with:rates', 'integer', 'min:1', 'max:255'],
            'rates.*.billing_unit' => ['required_with:rates', 'string', Rule::in(BillingUnit::values())],
            'rates.*.is_active' => ['nullable', 'boolean'],
            'rates.*.currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::in(array_keys(app(MoneyConverter::class)->scales())),
            ],
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
                ? UnitRate::find($rate['id'])
                : null;
            $currency = isset($rate['id'])
                ? $storedRate?->currency
                : (is_string($rate['currency'] ?? null) ? $rate['currency'] : null);

            $rules["rates.{$index}.amount"] = ['required_with:rates', new MoneyAmount($currency)];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
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
            UnitRate::query()
                ->where('unit_id', $this->route('unit')->id)
                ->when($submittedIds !== [], fn ($query) => $query->whereNotIn('id', $submittedIds))
                ->get()
                ->each(function (UnitRate $rate) use (&$seen): void {
                    try {
                        $currency = $rate->currency;
                    } catch (\Throwable) {
                        return;
                    }

                    $key = implode('|', [
                        $rate->billing_interval,
                        $rate->billing_unit->value,
                        $currency,
                    ]);
                    $seen[$key] = $rate->id;
                });

            foreach ($rates as $index => $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                $storedRate = isset($rate['id']) && is_scalar($rate['id'])
                    ? UnitRate::find($rate['id'])
                    : null;
                try {
                    $currency = $storedRate?->currency
                        ?? app(MoneyConverter::class)->normalizeCurrency($rate['currency'] ?? null);
                } catch (\Throwable) {
                    continue;
                }
                $key = implode('|', [
                    $rate['billing_interval'] ?? '',
                    $rate['billing_unit'] ?? '',
                    $currency,
                ]);
                $rateId = isset($rate['id']) && is_scalar($rate['id'])
                    ? (int) $rate['id']
                    : -($index + 1);

                if (array_key_exists($key, $seen) && $seen[$key] !== $rateId) {
                    $validator->errors()->add(
                        "rates.{$index}.currency",
                        __('A unit cannot have duplicate rates for the same billing period and currency.'),
                    );
                }

                $seen[$key] = $rateId;

                if ($storedRate) {
                    if (isset($rate['billing_interval'])
                        && is_numeric($rate['billing_interval'])
                        && (int) $rate['billing_interval'] !== $storedRate->billing_interval
                    ) {
                        $validator->errors()->add(
                            "rates.{$index}.billing_interval",
                            __('An existing unit-rate billing interval cannot be changed; add a new rate variant instead.'),
                        );
                    }

                    if (isset($rate['billing_unit'])
                        && is_string($rate['billing_unit'])
                        && $storedRate->billing_unit->value !== $rate['billing_unit']
                    ) {
                        $validator->errors()->add(
                            "rates.{$index}.billing_unit",
                            __('An existing unit-rate billing unit cannot be changed; add a new rate variant instead.'),
                        );
                    }

                    if (isset($rate['currency']) && $storedRate->currency !== $rate['currency']) {
                        $validator->errors()->add(
                            "rates.{$index}.currency",
                            __('An existing unit-rate currency cannot be changed; add a new rate variant instead.'),
                        );
                    }
                }
            }
        }];
    }
}
