<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class MarketplaceBrowseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
