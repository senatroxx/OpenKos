<?php

namespace App\Http\Requests;

use App\Enums\BillingUnit;
use App\Enums\RoomStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRoomRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms')
                    ->ignore($this->route('room')->id)
                    ->where(fn ($q) => $q->where('property_id', $this->route('property')->id)),
            ],
            'floor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:0', 'max:255'],
            'status' => ['nullable', new Enum(RoomStatus::class)],
            'notes' => ['nullable', 'string', 'max:65535'],
            'rates' => ['nullable', 'array'],
            'rates.*.id' => ['nullable', 'integer', 'exists:room_rates,id'],
            'rates.*.billing_interval' => ['required_with:rates', 'integer', 'min:1'],
            'rates.*.billing_unit' => ['required_with:rates', 'string', Rule::in(BillingUnit::values())],
            'rates.*.amount' => ['required_with:rates', 'numeric', 'min:0'],
            'rates.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
