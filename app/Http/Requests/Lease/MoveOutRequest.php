<?php

namespace App\Http\Requests\Lease;

use App\Data\Lease\DepositSettlementData;
use App\Enums\DepositSettlementStatus;
use App\Models\Lease;
use App\Rules\MoneyAmount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lease = $this->route('lease');
        $sourceUnitId = $lease instanceof Lease ? $lease->unit_id : null;
        $settlementPresent = is_array($this->input('settlement'));

        return [
            'move_out_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'deposit_returned' => ['nullable', 'boolean'],
            'deposit_refund_amount' => ['nullable', new MoneyAmount($lease instanceof Lease ? $lease->currency : null)],
            'notes' => ['nullable', 'string', 'max:65535'],
            'move_to_another_unit' => ['nullable', 'boolean'],
            'target_unit_id' => ['nullable', 'integer', Rule::notIn([$sourceUnitId]), 'exists:units,id'],
            'settlement' => ['nullable', 'array'],
            'settlement.status' => [Rule::requiredIf($settlementPresent), Rule::in(DepositSettlementStatus::values())],
            'settlement.settlement_date' => ['nullable', 'date'],
            'settlement.refund_amount' => ['nullable', new MoneyAmount($lease instanceof Lease ? $lease->currency : null)],
            'settlement.deductions' => ['nullable', 'array'],
            'settlement.deductions.*.amount' => ['required', new MoneyAmount($lease instanceof Lease ? $lease->currency : null)],
            'settlement.deductions.*.reason' => ['required', 'string', 'max:255'],
            'settlement.deductions.*.description' => ['nullable', 'string', 'max:65535'],
            'settlement.refund_reference' => ['nullable', 'string', 'max:255'],
            'settlement.notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function settlementData(): ?DepositSettlementData
    {
        $settlement = $this->validated('settlement');

        return is_array($settlement) ? DepositSettlementData::fromArray($settlement) : null;
    }
}
