<?php

namespace App\Actions\Inspections;

use App\Models\Inspection;
use Illuminate\Support\Facades\DB;

final class UpdateInspection
{
    /**
     * @param  array{notes?: string|null, damage_observations?: string|null, items: array<int, array{id: int, condition?: string|null, notes?: string|null}>}  $attributes
     */
    public function execute(Inspection $inspection, array $attributes): Inspection
    {
        return DB::transaction(function () use ($inspection, $attributes): Inspection {
            $lockedInspection = Inspection::query()
                ->whereKey($inspection->id)
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedInspection->isCompleted(), 422, __('Completed inspections are immutable.'));

            $submittedItems = collect($attributes['items'])->keyBy('id');
            $expectedIds = $lockedInspection->items->modelKeys();
            $submittedIds = $submittedItems->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
            sort($expectedIds);

            abort_unless($submittedIds === $expectedIds, 422, __('Inspection items do not match this inspection.'));

            $lockedInspection->update([
                'notes' => $attributes['notes'] ?? null,
                'damage_observations' => $attributes['damage_observations'] ?? null,
            ]);

            foreach ($lockedInspection->items as $item) {
                $itemAttributes = $submittedItems->get($item->id);
                $item->update([
                    'condition' => $itemAttributes['condition'] ?? null,
                    'notes' => $itemAttributes['notes'] ?? null,
                ]);
            }

            return $lockedInspection->load('items');
        });
    }
}
