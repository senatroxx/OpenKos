<?php

namespace App\Http\Requests\Reservation;

use App\Data\Lease\CreateLeaseData;
use App\Enums\ApplicationTargetType;
use App\Enums\BillingStrategy;
use App\Enums\BillingUnit;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLeaseFromReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->route('reservation');
        $application = $reservation->application;
        $unit = $reservation->unit;

        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['nullable', 'string', 'max:64'],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['nullable', 'string', Rule::in(BillingUnit::values())],
            'billing_strategy' => ['nullable', 'string', Rule::in(BillingStrategy::values())],
            'unit_rate_id' => [
                Rule::prohibitedIf($unit === null), 'nullable', 'integer',
                Rule::exists('unit_rates', 'id')->where('unit_id', $unit?->id),
            ],
            'unit_type_rate_id' => [
                Rule::prohibitedIf($unit === null), 'nullable', 'integer', 'prohibits:unit_rate_id',
                Rule::exists('unit_type_rates', 'id')->where('unit_type_id', $application->unit_type_id),
            ],
            'property_rate_id' => [
                Rule::requiredIf($application->target_type === ApplicationTargetType::WholeProperty),
                Rule::prohibitedIf($unit !== null),
                'nullable', 'integer',
                Rule::exists('property_rates', 'id')->where('property_id', $application->property_id),
            ],
            'deposit_amount' => ['nullable', 'string', 'max:64'],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_refund_amount' => ['nullable', 'string', 'max:64'],
            'deposit_refunded_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function toData(): CreateLeaseData
    {
        $data = $this->validated();

        return new CreateLeaseData(
            tenantIds: [],
            startDate: $data['start_date'],
            endDate: $data['end_date'] ?? null,
            rentAmount: $data['rent_amount'] ?? null,
            billingInterval: isset($data['billing_interval']) ? (int) $data['billing_interval'] : null,
            billingUnit: $data['billing_unit'] ?? null,
            billingStrategy: $data['billing_strategy'] ?? null,
            unitRateId: isset($data['unit_rate_id']) ? (int) $data['unit_rate_id'] : null,
            depositAmount: $data['deposit_amount'] ?? null,
            depositPaidAt: $data['deposit_paid_at'] ?? null,
            depositRefundAmount: $data['deposit_refund_amount'] ?? null,
            depositRefundedAt: $data['deposit_refunded_at'] ?? null,
            rentDueDay: isset($data['rent_due_day']) ? (int) $data['rent_due_day'] : null,
            notes: $data['notes'] ?? null,
            propertyRateId: isset($data['property_rate_id']) ? (int) $data['property_rate_id'] : null,
            unitTypeRateId: isset($data['unit_type_rate_id']) ? (int) $data['unit_type_rate_id'] : null,
        );
    }
}
