<?php

namespace App\Actions\Units;

use App\Data\Unit\BulkAssignUnitTypeData;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use App\Results\Unit\BulkAssignUnitTypeResult;
use Illuminate\Support\Facades\DB;

class AssignUnitTypeToUnits
{
    public function execute(Property $property, BulkAssignUnitTypeData $data): BulkAssignUnitTypeResult
    {
        return DB::transaction(function () use ($property, $data): BulkAssignUnitTypeResult {
            $unitType = UnitType::query()
                ->where('property_id', $property->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->find($data->unitTypeId);

            if ($unitType === null) {
                return BulkAssignUnitTypeResult::error(
                    __('The selected Unit Type is no longer available.'),
                    'unit_type_id',
                );
            }

            $units = $property->units()
                ->whereKey($data->unitIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($units->count() !== count($data->unitIds)) {
                return BulkAssignUnitTypeResult::error(
                    __('One or more selected Units are no longer available.'),
                    'unit_ids',
                );
            }

            Unit::query()
                ->whereKey($units->modelKeys())
                ->update([
                    'unit_type_id' => $unitType->id,
                    'updated_at' => now(),
                ]);

            return BulkAssignUnitTypeResult::success();
        });
    }
}
