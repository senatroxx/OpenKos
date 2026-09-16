<?php

namespace App\Actions\Inspections;

use App\Models\InspectionTemplate;
use Illuminate\Support\Facades\DB;

final class UpdateInspectionTemplate
{
    /**
     * @param  array{name: string, inspection_type: string, is_active: bool, items: array<int, array{id?: int|null, label: string, description?: string|null}>}  $attributes
     */
    public function execute(InspectionTemplate $template, array $attributes): InspectionTemplate
    {
        return DB::transaction(function () use ($template, $attributes): InspectionTemplate {
            $lockedTemplate = InspectionTemplate::query()
                ->lockForUpdate()
                ->findOrFail($template->id);
            $items = $attributes['items'];
            unset($attributes['items']);

            $lockedTemplate->update($attributes);

            $existingItems = $lockedTemplate->items()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $submittedIds = [];

            foreach ($items as $position => $item) {
                $itemId = $item['id'] ?? null;
                $itemAttributes = [
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                    'position' => $position,
                ];

                if ($itemId !== null) {
                    abort_unless($existingItems->has($itemId), 422, __('Checklist item does not belong to this template.'));
                    $existingItems->get($itemId)->update($itemAttributes);
                    $submittedIds[] = (int) $itemId;

                    continue;
                }

                $lockedTemplate->items()->create($itemAttributes);
            }

            $existingItems
                ->except($submittedIds)
                ->each
                ->delete();

            return $lockedTemplate->load('items');
        });
    }
}
