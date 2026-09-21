<?php

namespace App\Actions\Units;

use App\Models\Lease;
use App\Models\Unit;
use App\Results\Unit\DeleteUnitResult;
use Illuminate\Support\Facades\DB;

final class DeleteUnit
{
    public function execute(Unit $unit): DeleteUnitResult
    {
        return DB::transaction(function () use ($unit): DeleteUnitResult {
            $lockedUnit = Unit::query()->lockForUpdate()->findOrFail($unit->id);

            if (Lease::where('unit_id', $lockedUnit->id)->active()->exists()) {
                return DeleteUnitResult::blocked();
            }

            $lockedUnit->delete();

            return DeleteUnitResult::success();
        });
    }
}
