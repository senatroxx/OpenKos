<?php

namespace App\Http\Requests\RecurringExpense;

use App\Enums\BillingUnit;
use App\Models\Property;
use App\Models\RecurringExpense;
use App\Rules\MoneyAmount;
use App\Services\Payments\MoneyConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $recurringExpense = $this->route('recurringExpense');

        return $recurringExpense instanceof RecurringExpense
            && ($this->user()?->can('update', $recurringExpense) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'property_id' => $this->propertyRules(),
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'amount' => ['required', new MoneyAmount($this->input('currency'), allowZero: false)],
            'currency' => [
                'required', 'string', 'size:3', Rule::in(array_keys(app(MoneyConverter::class)->scales())),
            ],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'billing_interval' => ['required', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['required', 'string', Rule::in(BillingUnit::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    /** @return array<int, mixed> */
    private function propertyRules(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('properties', 'id'),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->user()->isOwner()) {
                    return;
                }

                if (! Property::query()->whereKey($value)->whereHas(
                    'users', fn ($query) => $query->whereKey($this->user()->id),
                )->exists()) {
                    $fail(__('You do not have access to this property.'));
                }
            },
        ];
    }
}
