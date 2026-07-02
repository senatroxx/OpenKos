<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', Rule::in(MaintenancePriority::values())],
            'status' => ['nullable', 'string', Rule::in(MaintenanceStatus::values())],
            'resolution_notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'resolved_at' => ['nullable', 'date'],
            'restore_room' => ['nullable', 'boolean'],
        ];
    }
}
