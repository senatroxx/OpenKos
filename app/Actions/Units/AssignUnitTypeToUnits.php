<?php

namespace App\Actions\Units;

use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignUnitTypeToUnits
{
    /**
     * @param  list<int>  $unitIds
     */
    public function execute(Property $property, array $unitIds, int $unitTypeId): void
    {
        DB::transaction(function () use ($property, $unitIds, $unitTypeId): void {
            $unitType = UnitType::query()
                ->where('property_id', $property->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->find($unitTypeId);

            if ($unitType === null) {
                throw ValidationException::withMessages([
                    'unit_type_id' => __('The selected Unit Type is no longer available.'),
                ]);
            }

            $units = $property->units()
                ->whereKey($unitIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($units->count() !== count($unitIds)) {
                throw ValidationException::withMessages([
                    'unit_ids' => __('One or more selected Units are no longer available.'),
                ]);
            }

            Unit::query()
                ->whereKey($units->modelKeys())
                ->update([
                    'unit_type_id' => $unitType->id,
                    'updated_at' => now(),
                ]);
        });
    }
}
