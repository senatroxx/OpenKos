<?php

namespace App\Http\Requests\Inspection;

use App\Enums\InspectionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInspectionTemplateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'inspection_type' => ['required', Rule::enum(InspectionType::class)],
            'is_active' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
