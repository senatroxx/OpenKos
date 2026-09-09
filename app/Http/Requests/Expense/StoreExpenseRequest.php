<?php

namespace App\Http\Requests\Expense;

use App\Models\Property;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('expenses.create') ?? false;
    }

    public function rules(): array
    {
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
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where('is_active', true),
            ],
            'amount' => [
                'required',
                new MoneyAmount($this->input('currency'), allowZero: false),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(array_keys(app(MoneyConverter::class)->scales())),
            ],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }
}
