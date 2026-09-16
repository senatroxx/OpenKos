<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyRentalMode;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePropertyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::exists('property_types', 'slug')->where('is_active', true)],
            'rental_mode' => ['sometimes', Rule::enum(PropertyRentalMode::class)],
            'slug' => ['nullable', 'string', 'max:255', 'unique:properties,slug,'.$this->route('property')?->id],
            'address' => ['nullable', 'string', 'max:65535'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'description' => ['nullable', 'string', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->isEmpty() || ! $this->exists('rental_mode')) {
                return;
            }

            $property = $this->route('property');
            if (! $property instanceof Property) {
                return;
            }

            $requestedMode = PropertyRentalMode::tryFrom((string) $this->input('rental_mode'));
            if ($requestedMode === null || $requestedMode === $property->rental_mode) {
                return;
            }

            if ($error = $property->rentalModeChangeError($requestedMode)) {
                $validator->errors()->add('rental_mode', $error);
            }
        }];
    }
}
