<?php

namespace App\Http\Requests\Reservation;

use App\Data\Reservation\RequestReservationData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
            'move_in_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function toData(): RequestReservationData
    {
        return new RequestReservationData($this->validated('move_in_date'));
    }
}
