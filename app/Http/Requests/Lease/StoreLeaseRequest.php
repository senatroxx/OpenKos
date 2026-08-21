<?php

namespace App\Http\Requests\Lease;

use App\Enums\BillingStrategy;
use App\Enums\BillingUnit;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Rules\MoneyAmount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');
        $rate = $this->integer('unit_rate_id') > 0
            ? UnitRate::query()
                ->whereKey($this->integer('unit_rate_id'))
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->first()
            : $unit->defaultActiveRate();
        $existingLease = $unit->leases()->where('status', 'active')->first();
        $currency = $existingLease?->currency ?? $rate?->currency;

        return [
            'tenant_ids' => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['required', 'integer', 'distinct', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['nullable', new MoneyAmount($currency)],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['nullable', 'string', Rule::in(BillingUnit::values())],
            'billing_strategy' => ['nullable', 'string', Rule::in(BillingStrategy::values())],
            'unit_rate_id' => [
                'nullable',
                'integer',
                Rule::exists('unit_rates', 'id')
                    ->where('unit_id', $unit->id)
                    ->where('is_active', true),
            ],
            'deposit_amount' => ['nullable', new MoneyAmount($currency)],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_refund_amount' => ['nullable', new MoneyAmount($currency)],
            'deposit_refunded_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
