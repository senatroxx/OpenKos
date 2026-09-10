<?php

namespace App\Http\Requests\Amenity;

use App\Enums\AmenityScope;
use App\Models\Amenity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAmenityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'scope' => ['nullable', Rule::enum(AmenityScope::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'scope' => $this->input('scope', AmenityScope::Property->value),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $exists = Amenity::query()
                ->where('owner_property_id', $this->route('property')->id)
                ->whereRaw('LOWER(name) = LOWER(?)', [$this->input('name')])
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', __('The amenity name is already used by this property.'));
            }
        }];
    }
}
