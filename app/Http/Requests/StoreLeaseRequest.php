<?php

namespace App\Http\Requests;

use App\Models\Lease;
use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
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

    public function ensureRoomAvailable(Room $room): void
    {
        $hasActiveLease = Lease::query()
            ->where('room_id', $room->id)
            ->where('status', 'active')
            ->exists();

        abort_if($hasActiveLease, 422, __('Room already has an active lease.'));
    }
}
