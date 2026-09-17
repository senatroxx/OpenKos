<?php

namespace App\Http\Requests\Lease;

use App\Enums\BillingStrategy;
use App\Enums\BillingUnit;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Property $property */
        $property = $this->route('property');
        $rate = $this->integer('property_rate_id') > 0
            ? PropertyRate::query()
                ->whereKey($this->integer('property_rate_id'))
                ->where('property_id', $property->id)
                ->where('is_active', true)
                ->first()
            : $property->defaultActivePropertyRate();

        return [
            'tenant_ids' => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['required', 'integer', 'distinct', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['nullable', new MoneyAmount($rate?->currency)],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['nullable', 'string', Rule::in(BillingUnit::values())],
            'billing_strategy' => ['nullable', 'string', Rule::in(BillingStrategy::values())],
            'property_rate_id' => [
                'required',
                'integer',
                Rule::exists('property_rates', 'id')
                    ->where('property_id', $property->id)
                    ->where('is_active', true),
            ],
            'unit_rate_id' => ['prohibited'],
            'deposit_amount' => ['nullable', new MoneyAmount($rate?->currency)],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_refund_amount' => ['nullable', new MoneyAmount($rate?->currency)],
            'deposit_refunded_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
