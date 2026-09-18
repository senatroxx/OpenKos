<?php

namespace App\Http\Requests\Unit;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignUnitTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', [Unit::class, $this->route('property')]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Property $property */
        $property = $this->route('property');

        return [
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('units', 'id')->where(fn ($query) => $query
                    ->where('property_id', $property->id)
                    ->whereNull('deleted_at')),
            ],
            'unit_type_id' => [
                'required',
                'integer',
                Rule::exists('unit_types', 'id')->where(fn ($query) => $query
                    ->where('property_id', $property->id)
                    ->where('is_active', true)),
            ],
        ];
    }
}
