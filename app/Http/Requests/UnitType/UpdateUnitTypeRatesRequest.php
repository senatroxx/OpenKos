<?php

namespace App\Http\Requests\UnitType;

use App\Enums\BillingUnit;
use App\Models\UnitTypeRate;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUnitTypeRatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('unitType')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'updated_at' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*' => ['array'],
            'rates.*.id' => [
                'nullable', 'integer', 'distinct:strict',
                Rule::exists('unit_type_rates', 'id')->where('unit_type_id', $this->route('unitType')->id),
            ],
            'rates.*.billing_interval' => ['required', 'integer', 'min:1', 'max:255'],
            'rates.*.billing_unit' => ['required', 'string', Rule::in(BillingUnit::values())],
            'rates.*.currency' => ['required', 'string', 'size:3', Rule::in(array_keys(app(MoneyConverter::class)->scales()))],
            'rates.*.is_active' => ['nullable', 'boolean'],
            'rates.*.effective_from' => ['nullable', 'date'],
            'rates.*.effective_until' => ['nullable', 'date', 'after_or_equal:rates.*.effective_from'],
        ];

        foreach ($this->input('rates', []) as $index => $rate) {
            $currency = is_array($rate) && is_string($rate['currency'] ?? null) ? $rate['currency'] : null;
            $rules["rates.{$index}.amount"] = ['required', ...($currency ? [new MoneyAmount($currency)] : [])];
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];
            foreach ($this->input('rates', []) as $index => $rate) {
                if (! is_array($rate)) {
                    continue;
                }

                $stored = isset($rate['id'])
                    ? UnitTypeRate::query()
                        ->where('unit_type_id', $this->route('unitType')->id)
                        ->find($rate['id'])
                    : null;
                $currency = strtoupper((string) ($stored?->currency ?? $rate['currency'] ?? ''));
                $key = implode('|', [$rate['billing_interval'] ?? '', $rate['billing_unit'] ?? '', $currency]);
                if (isset($seen[$key]) && $seen[$key] !== ($rate['id'] ?? -($index + 1))) {
                    $validator->errors()->add("rates.{$index}.currency", __('A Unit Type cannot have duplicate rates for the same billing period and currency.'));
                }
                $seen[$key] = $rate['id'] ?? -($index + 1);
            }
        }];
    }
}
