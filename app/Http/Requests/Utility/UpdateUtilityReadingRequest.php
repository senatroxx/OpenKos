<?php

namespace App\Http\Requests\Utility;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUtilityReadingRequest extends FormRequest
{
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
            'reading_date' => ['sometimes', 'date'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date', 'after_or_equal:period_start'],
            'previous_reading' => ['sometimes', 'regex:/\A(?:0|[1-9]\d*)(?:\.\d{1,3})?\z/D'],
            'current_reading' => ['required', 'regex:/\A(?:0|[1-9]\d*)(?:\.\d{1,3})?\z/D'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->date('reading_date') || ! $this->date('period_start') || ! $this->date('period_end')) {
                return;
            }

            if ($this->date('reading_date')->lt($this->date('period_start')) || $this->date('reading_date')->gt($this->date('period_end'))) {
                $validator->errors()->add('reading_date', __('The reading date must fall within its utility period.'));
            }
        }];
    }
}
