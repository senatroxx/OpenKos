<?php

namespace App\Http\Requests\UnitType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitTypeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('unitType')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
