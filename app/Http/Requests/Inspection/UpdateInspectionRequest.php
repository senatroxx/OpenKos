<?php

namespace App\Http\Requests\Inspection;

use App\Enums\InspectionItemCondition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInspectionRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:65535'],
            'damage_observations' => ['nullable', 'string', 'max:65535'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.condition' => ['nullable', Rule::enum(InspectionItemCondition::class)],
            'items.*.notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
