<?php

namespace App\Http\Requests\Unit;

use App\Enums\BillingUnit;
use App\Enums\UnitStatus;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreUnitRequest extends FormRequest
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
                Rule::unique('units')->where(fn ($q) => $q->where('property_id', $this->route('property')->id)),
            ],
            'floor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:0', 'max:255'],
            'status' => ['nullable', new Enum(UnitStatus::class)],
            'notes' => ['nullable', 'string', 'max:65535'],
            'rates' => ['sometimes', 'array'],
            'rates.*' => ['array'],
            'rates.*.billing_interval' => ['required_with:rates', 'integer', 'min:1', 'max:255'],
            'rates.*.billing_unit' => ['required_with:rates', 'string', Rule::in(BillingUnit::values())],
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

            $currency = $rate['currency'] ?? null;
            $rules["rates.{$index}.amount"] = [
                'required_with:rates',
                new MoneyAmount(is_string($currency) ? $currency : null),
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];
            $rates = $this->input('rates', []);

            if (! is_array($rates)) {
                return;
            }

            foreach ($rates as $index => $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                try {
                    $currency = app(MoneyConverter::class)->normalizeCurrency($rate['currency'] ?? null);
                } catch (\Throwable) {
                    continue;
                }
                $key = implode('|', [
                    $rate['billing_interval'] ?? '',
                    $rate['billing_unit'] ?? '',
                    $currency,
                ]);

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "rates.{$index}.currency",
                        __('A unit cannot have duplicate active rates for the same billing period and currency.'),
                    );
                }

                $seen[$key] = true;
            }
        }];
    }
}
