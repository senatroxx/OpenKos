<?php

namespace App\Http\Requests\Settings;

use App\Enums\AmenityIcon;
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
            if (Amenity::query()->whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$this->input('name')])->exists()) {
                $validator->errors()->add('name', __('The amenity name is already in use.'));
            }
        }];
    }
}
