<?php

namespace App\Http\Requests\Expense;

use App\Models\Expense;
use App\Models\Property;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            && ! $expense->isVoided()
            && ($this->user()?->can('update', $expense) ?? false);
    }

    public function rules(): array
    {
        $expense = $this->route('expense');

        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->user()->isOwner()) {
                        return;
                    }

                    if (! Property::query()
                        ->whereKey($value)
                        ->whereHas('users', fn ($query) => $query->whereKey($this->user()->id))
                        ->exists()) {
                        $fail(__('You do not have access to this property.'));
                    }
                },
            ],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'amount' => [
                'required',
                new MoneyAmount($expense instanceof Expense ? (string) $expense->currency : null, allowZero: false),
            ],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }
}
