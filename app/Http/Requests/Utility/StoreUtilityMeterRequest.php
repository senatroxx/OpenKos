<?php

namespace App\Http\Requests\Utility;

use App\Enums\UtilityMeterType;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreUtilityMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'utility_type' => ['required', new Enum(UtilityMeterType::class)],
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('utility_meters', 'identifier')
                    ->where(fn ($query) => $query->where('unit_id', $this->route('unit')->id)),
            ],
            'measurement_unit' => ['required', 'string', 'max:50'],
            'rate' => ['required', new MoneyAmount($this->input('currency'), allowZero: false)],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(array_keys(app(MoneyConverter::class)->scales())),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $currency = $this->input('currency');

            if (is_string($currency) && ! app(InstallationCurrencySettings::class)->supports($currency)) {
                $validator->errors()->add('currency', __('This currency is not enabled for utility rates.'));
            }
        }];
    }
}
