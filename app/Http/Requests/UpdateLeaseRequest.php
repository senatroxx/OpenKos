<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_refunded_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
