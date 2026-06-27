<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'paid_at' => ['required', 'date'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'proof' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function ensureLeaseIsActive(): void
    {
        $lease = $this->route('lease');

        if (! $lease instanceof Lease || $lease->status !== 'active') {
            abort(422, __('Lease must be active to record payments.'));
        }
    }

    public function ensureNoDuplicatePayment(Lease $lease): void
    {
        $exists = $lease->payments()
            ->whereYear('period_start', $this->period_year)
            ->whereMonth('period_start', $this->period_month)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($exists) {
            abort(422, __('A payment for this billing period already exists.'));
        }
    }
}
