<?php

namespace App\Http\Requests\Lease;

use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveLeaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $sourceUnitId = $unit instanceof Unit ? $unit->id : null;

        return [
            'target_unit_id' => ['required', 'integer', Rule::notIn([$sourceUnitId]), 'exists:units,id'],
        ];
    }
}
