<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class MarketplaceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    public function rules(): array
    {
        return [
            'plugin_id' => [
                'required',
                'string',
                'max:255',
                'regex:/\A[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*\z/',
            ],
            'version' => [
                'required',
                'string',
                'max:50',
                'regex:/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?\z/',
            ],
        ];
    }
}
