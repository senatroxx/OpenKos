<?php

namespace App\Http\Requests\Utility;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUtilityReadingCorrectionRequest extends FormRequest
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
            'current_reading' => ['required', 'regex:/\A(?:0|[1-9]\d*)(?:\.\d{1,3})?\z/D'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
