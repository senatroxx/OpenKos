<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, bool $portalProfile = false): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $portalProfile
                ? ['required', 'string', 'email', 'max:255', Rule::in([$this->user()->email])]
                : $this->emailRules($userId),
            'phone' => [
                $portalProfile ? 'required' : 'nullable',
                'string',
                'max:20',
                'regex:/^\+[1-9]\d{6,14}$/',
            ],
            'id_card_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+[1-9]\d{6,14}$/',
            ],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
