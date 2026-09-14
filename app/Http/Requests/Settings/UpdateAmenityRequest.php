<?php

namespace App\Http\Requests\Settings;

use App\Enums\AmenityIcon;
use App\Enums\AmenityScope;
use App\Models\Amenity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAmenityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
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
            'scope' => ['required', Rule::enum(AmenityScope::class)],
            'icon' => ['nullable', Rule::enum(AmenityIcon::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Amenity $amenity */
            $amenity = $this->route('amenity');
            $name = $this->input('name');

            if (Amenity::query()
                ->whereKeyNot($amenity->id)
                ->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$name])
                ->exists()) {
                $validator->errors()->add('name', __('The amenity name is already in use.'));
            }

            $scope = AmenityScope::tryFrom((string) $this->input('scope'));

            if ($scope && ! $scope->allowsProperty() && $amenity->properties()->exists()) {
                $validator->errors()->add('scope', __('This scope is incompatible with existing Property assignments.'));
            }

            if ($scope && ! $scope->allowsUnitType() && $amenity->unitTypes()->exists()) {
                $validator->errors()->add('scope', __('This scope is incompatible with existing Unit Type assignments.'));
            }
        }];
    }
}
