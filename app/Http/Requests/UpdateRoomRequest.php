<?php

namespace App\Http\Requests;

use App\Enums\RoomStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRoomRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:255'],
            'status' => ['nullable', new Enum(RoomStatus::class)],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
