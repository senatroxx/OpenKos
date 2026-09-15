<?php

namespace App\Http\Requests\Settings;

use App\Enums\PropertyRentalMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255', 'unique:property_types,label'],
            'default_rental_mode' => ['sometimes', Rule::enum(PropertyRentalMode::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
