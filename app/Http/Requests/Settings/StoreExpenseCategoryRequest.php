<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'label')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
