<?php

namespace App\Http\Requests\UnitType;

use App\Models\Amenity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUnitTypeRequest extends FormRequest
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
        $propertyId = $this->route('property')->id;
        $unitType = $this->route('unitType');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('unit_types')->ignore($unitType->id)->where(fn ($query) => $query->where('property_id', $propertyId))],
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
            $unitType = $this->route('unitType');
            $existingIds = $unitType->amenities()->pluck('amenities.id')->map(fn (mixed $id): int => (int) $id)->all();
            $ids = collect($this->input('amenity_ids', []))
                ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
                ->map(fn (mixed $id): int => (int) $id)
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            $propertyId = $this->route('property')->id;
            $amenities = Amenity::query()->whereIn('id', $ids)->get(['id', 'owner_property_id', 'is_active']);

            foreach ($ids as $index => $id) {
                $amenity = $amenities->firstWhere('id', $id);

                if (! $amenity || ($amenity->owner_property_id !== null && $amenity->owner_property_id !== $propertyId)) {
                    $validator->errors()->add("amenity_ids.{$index}", __('The selected amenity is not available for this property.'));

                    continue;
                }

                if (! $amenity->is_active && ! in_array($id, $existingIds, true)) {
                    $validator->errors()->add("amenity_ids.{$index}", __('Inactive amenities cannot be newly assigned.'));
                }
            }
        }];
    }
}
