<?php

namespace App\Http\Requests\Payment;

use Illuminate\Validation\Rule;

class StoreTenantPortalPaymentRequest extends StorePaymentRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            ...$this->paymentRules(),
        ];
    }
}
