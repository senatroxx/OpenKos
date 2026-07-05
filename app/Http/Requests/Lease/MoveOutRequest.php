<?php

namespace App\Http\Requests\Lease;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveOutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'move_out_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'deposit_returned' => ['nullable', 'boolean'],
            'deposit_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'move_to_another_unit' => ['nullable', 'boolean'],
            'target_unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ];
    }
}
