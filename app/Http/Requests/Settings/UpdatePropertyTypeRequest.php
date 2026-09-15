<?php

namespace App\Http\Requests\Settings;

use App\Enums\PropertyRentalMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyTypeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255', Rule::unique('property_types', 'label')->ignore($this->route('propertyType'))],
            'default_rental_mode' => ['sometimes', Rule::enum(PropertyRentalMode::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
