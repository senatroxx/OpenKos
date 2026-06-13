<?php

namespace App\Http\Requests\Role;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloneRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
