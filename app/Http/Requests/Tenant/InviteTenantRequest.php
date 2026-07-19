<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class InviteTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'send_invite' => ['nullable', 'boolean'],
        ];
    }
}
