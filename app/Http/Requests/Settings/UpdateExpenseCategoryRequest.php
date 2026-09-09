<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        $category = $this->route('expenseCategory');

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'label')->ignore($category?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
