<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
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
        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ];
    }

    public function unitId(): ?int
    {
        $unitId = $this->validated('unit_id');

        return $unitId === null ? null : (int) $unitId;
    }
}
