<?php

namespace App\Http\Requests;

use App\Enums\BillingUnit;
use App\Models\Room;
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
        return [
            'tenant_ids' => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['required', 'integer', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['nullable', 'string', Rule::in(BillingUnit::values())],
            'room_rate_id' => ['nullable', 'integer', 'exists:room_rates,id'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_refunded_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public function ensureCapacityAvailable(Room $room): void
    {
        $tenantCount = count($this->tenant_ids);
        $activeTenantsCount = $room->leases()
            ->where('status', 'active')
            ->withCount('tenants')
            ->get()
            ->sum('tenants_count');

        $totalOccupants = $activeTenantsCount + $tenantCount;

        abort_if($totalOccupants > $room->capacity, 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));
    }
}
