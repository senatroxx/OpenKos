<?php

namespace App\Http\Requests\Expense;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class VoidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            && ! $expense->isVoided()
            && ($this->user()?->can('delete', $expense) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
