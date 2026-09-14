<?php

namespace App\Http\Requests\Lease;

use App\Data\Lease\DepositSettlementData;
use App\Enums\DepositSettlementStatus;
use App\Models\Lease;
use App\Rules\MoneyAmount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositSettlementRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lease = $this->route('lease');
        $currency = $lease instanceof Lease
            ? ($lease->depositSettlement?->currency ?? $lease->currency)
            : null;

        return [
            'status' => ['required', Rule::in(DepositSettlementStatus::values())],
            'settlement_date' => ['nullable', 'date'],
            'refund_amount' => ['nullable', new MoneyAmount($currency)],
            'deductions' => ['nullable', 'array'],
            'deductions.*.amount' => ['required', new MoneyAmount($currency)],
            'deductions.*.reason' => ['required', 'string', 'max:255'],
            'deductions.*.description' => ['nullable', 'string', 'max:65535'],
            'refund_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function toData(): DepositSettlementData
    {
        return DepositSettlementData::fromArray($this->validated());
    }
}
