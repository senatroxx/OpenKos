<?php

namespace App\Http\Requests\Amenity;

use App\Models\Amenity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncAmenitiesRequest extends FormRequest
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
            'amenity_ids' => ['present', 'array'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $propertyId = $this->route('property')->id;
            $ids = collect($this->input('amenity_ids', []))->map(fn (mixed $id): int => (int) $id);
            $amenities = Amenity::query()->whereIn('id', $ids)->get(['id', 'owner_property_id', 'scope']);

            foreach ($ids as $index => $id) {
                $amenity = $amenities->firstWhere('id', $id);

                if (! $amenity || ! $amenity->scope->allowsProperty() || ($amenity->owner_property_id !== null && $amenity->owner_property_id !== $propertyId)) {
                    $validator->errors()->add("amenity_ids.{$index}", __('The selected amenity is not available for this property.'));
                }
            }
        }];
    }
}
