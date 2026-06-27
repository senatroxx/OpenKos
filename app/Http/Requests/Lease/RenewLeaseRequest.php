<?php

namespace App\Http\Requests\Lease;

use App\Data\Lease\RenewLeaseData;
use App\Enums\DepositHandling;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewLeaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rent_amount' => ['required', 'integer', 'min:1'],
            'extension_value' => ['required', 'integer', 'min:1', 'max:120'],
            'extension_unit' => ['required', Rule::in(['months', 'years'])],
            'deposit_handling' => ['required', Rule::in(DepositHandling::values())],
            'confirmed_outstanding' => ['boolean'],
        ];
    }

    public function toData(): RenewLeaseData
    {
        $lease = $this->route('lease');

        $extensionMonths = $this->extension_unit === 'years'
            ? $this->extension_value * 12
            : $this->extension_value;

        $endDate = CarbonImmutable::parse($lease->end_date)->addMonthsNoOverflow($extensionMonths);

        return new RenewLeaseData(
            endDate: $endDate,
            rentAmount: (int) $this->rent_amount,
            depositHandling: DepositHandling::from($this->deposit_handling),
            confirmedOutstanding: (bool) $this->confirmed_outstanding,
        );
    }
}
