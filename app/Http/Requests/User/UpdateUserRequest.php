<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'roles' => [$user->isOwner() ? 'nullable' : 'required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('is_active', true)->whereNot('name', Role::Owner->value)],
            'property_ids' => ['array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
