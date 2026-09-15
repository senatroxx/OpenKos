<?php

namespace App\Http\Requests\Inspection;

use App\Enums\InspectionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInspectionRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'inspection_type' => ['required', Rule::enum(InspectionType::class)],
            'inspection_template_id' => [
                'required',
                'integer',
                Rule::exists('inspection_templates', 'id')
                    ->where('is_active', true)
                    ->where('inspection_type', $this->string('inspection_type')->toString()),
            ],
            'inspection_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'damage_observations' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
