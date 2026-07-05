<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceTicketRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->user()->isOwner()) {
                        return;
                    }

                    $accessible = Property::query()
                        ->whereKey($value)
                        ->whereHas('users', fn ($q) => $q->whereKey($this->user()->id))
                        ->exists();

                    if (! $accessible) {
                        $fail(__('You do not have access to this property.'));
                    }
                },
            ],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('property_id', $this->input('property_id')),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(MaintenancePriority::values())],
            'block_unit' => ['nullable', 'boolean'],
            'move_tenant_to_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('property_id', $this->input('property_id')),
            ],
        ];
    }
}
