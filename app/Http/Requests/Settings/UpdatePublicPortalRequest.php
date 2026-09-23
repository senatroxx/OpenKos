<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'public_site_name' => ['nullable', 'string', 'max:255'],
            'public_homepage_title' => ['nullable', 'string', 'max:255'],
            'public_homepage_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
