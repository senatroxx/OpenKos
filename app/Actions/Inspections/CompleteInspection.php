<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CompleteInspection
{
    public function execute(Inspection $inspection, User $actor): Inspection
    {
        return DB::transaction(function () use ($inspection, $actor): Inspection {
            $lockedInspection = Inspection::query()
                ->whereKey($inspection->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedInspection->isCompleted(), 422, __('Inspection is already completed.'));
            abort_if($lockedInspection->items->isEmpty(), 422, __('An inspection must contain checklist items.'));
            abort_if($lockedInspection->items->contains(fn ($item): bool => $item->condition === null), 422, __('Every checklist item must have a condition before completion.'));

            $lockedInspection->update([
                'status' => InspectionStatus::Completed,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            return $lockedInspection->load('items');
        });
    }
}
