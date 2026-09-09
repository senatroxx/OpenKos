<?php

namespace App\Http\Requests\UnitType;

use App\Models\Amenity;
use App\Models\UnitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUnitTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:255'],
            'bathrooms' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'furnishing' => ['nullable', 'string', 'max:255'],
            'amenity_ids' => ['sometimes', 'array'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateName($validator);
            $this->validateAmenities($validator, []);
        }];
    }

    private function validateName(Validator $validator): void
    {
        $exists = UnitType::query()
            ->where('property_id', $this->route('property')->id)
            ->whereRaw('LOWER(name) = LOWER(?)', [$this->input('name')])
            ->exists();

        if ($exists) {
            $validator->errors()->add('name', __('The UnitType name is already used by this property.'));
        }
    }

    /**
     * @param  array<int, int>  $existingIds
     */
    private function validateAmenities(Validator $validator, array $existingIds): void
    {
        $ids = collect($this->input('amenity_ids', []))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $propertyId = $this->route('property')->id;
        $amenities = Amenity::query()->whereIn('id', $ids)->get(['id', 'owner_property_id', 'is_active']);

        foreach ($ids as $id) {
            $amenity = $amenities->firstWhere('id', $id);

            if (! $amenity || ($amenity->owner_property_id !== null && $amenity->owner_property_id !== $propertyId)) {
                $validator->errors()->add("amenity_ids.{$ids->search($id)}", __('The selected amenity is not available for this property.'));

                continue;
            }

            if (! $amenity->is_active && ! in_array($id, $existingIds, true)) {
                $validator->errors()->add("amenity_ids.{$ids->search($id)}", __('Inactive amenities cannot be newly assigned.'));
            }
        }
    }
}
