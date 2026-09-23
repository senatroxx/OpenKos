<?php

namespace App\Http\Requests\Application;

use App\Data\Application\TransitionApplicationData;
use App\Enums\Permission;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        return (bool) ($this->user()?->isOwner()
            || $this->user()?->can(Permission::TenantsView->value)
            || ($application instanceof Application && $application->user_id === $this->user()?->id));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:new,reviewing,accepted,rejected,withdrawn'],
            'operator_notes' => ['nullable', 'string', 'max:10000'],
            'applicant_feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): TransitionApplicationData
    {
        return new TransitionApplicationData(
            $this->string('status')->toString(),
            $this->input('operator_notes'),
            $this->input('applicant_feedback'),
        );
    }
}
