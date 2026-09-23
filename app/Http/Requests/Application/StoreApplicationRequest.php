<?php

namespace App\Http\Requests\Application;

use App\Data\Application\SubmitApplicationData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:whole_property,unit_type'],
            'property_slug' => ['required', 'string', 'max:255'],
            'unit_type_slug' => ['nullable', 'string', 'max:255'],
            'intended_move_in_date' => ['nullable', 'date'],
            'intended_move_in_timeframe' => ['nullable', 'string', 'max:100'],
            'applicant_phone' => ['nullable', 'string', 'max:30'],
            'applicant_message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): SubmitApplicationData
    {
        return new SubmitApplicationData(
            $this->string('target_type')->toString(),
            $this->string('property_slug')->toString(),
            $this->input('unit_type_slug'),
            $this->input('intended_move_in_date'),
            $this->input('intended_move_in_timeframe'),
            $this->input('applicant_phone'),
            $this->input('applicant_message'),
        );
    }
}
