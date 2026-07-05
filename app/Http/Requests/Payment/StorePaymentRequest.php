<?php

namespace App\Http\Requests\Payment;

use App\Enums\LeaseStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        if (! $lease instanceof Lease || $lease->status !== LeaseStatus::Active) {
            throw ValidationException::withMessages([
                'lease' => __('Lease must be active to record payments.'),
            ]);
        }
    }

    public function ensureNoDuplicatePayment(Lease $lease): void
    {
        $exists = $lease->payments()
            ->whereYear('period_start', $this->period_year)
            ->whereMonth('period_start', $this->period_month)
            ->where('status', '!=', PaymentStatus::Cancelled->value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period' => __('A payment for this billing period already exists.'),
            ]);
        }
    }
}
