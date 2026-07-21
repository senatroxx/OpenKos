<?php

namespace App\Http\Requests\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
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
        $lease = $this->route('lease');

        return [
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where('lease_id', $lease instanceof Lease ? $lease->id : null),
            ],
            ...$this->paymentRules(),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function paymentRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'proof' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function ensureLeaseIsActive(?Lease $lease = null): void
    {
        $lease ??= $this->route('lease');

        if (! $lease instanceof Lease || $lease->status !== LeaseStatus::Active) {
            throw ValidationException::withMessages([
                'lease' => __('Lease must be active to record payments.'),
            ]);
        }
    }

    public function ensureInvoiceIsPayable(Invoice $invoice): void
    {
        if (! in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'invoice_id' => __('This invoice is not payable.'),
            ]);
        }

        if ((float) $this->amount > (float) $invoice->outstanding) {
            throw ValidationException::withMessages([
                'amount' => __('Amount exceeds the invoice outstanding balance of :outstanding.', [
                    'outstanding' => number_format((float) $invoice->outstanding, 0, ',', '.'),
                ]),
            ]);
        }
    }
}
