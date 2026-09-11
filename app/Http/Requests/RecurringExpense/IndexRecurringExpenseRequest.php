<?php

namespace App\Http\Requests\RecurringExpense;

use Illuminate\Foundation\Http\FormRequest;

class IndexRecurringExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('expenses.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:active,paused,ended'],
        ];
    }
}
