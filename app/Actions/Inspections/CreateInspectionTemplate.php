<?php

namespace App\Actions\Inspections;

use App\Models\InspectionTemplate;
use Illuminate\Support\Facades\DB;

final class CreateInspectionTemplate
{
    /**
     * @param  array{name: string, inspection_type: string, items: array<int, array{label: string, description?: string|null}>}  $attributes
     */
    public function execute(array $attributes): InspectionTemplate
    {
        return DB::transaction(function () use ($attributes): InspectionTemplate {
            $items = $attributes['items'];
            unset($attributes['items']);

            $template = InspectionTemplate::create($attributes);

            $template->items()->createMany(array_map(
                static fn (array $item, int $position): array => [
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                    'position' => $position,
                ],
                $items,
                array_keys($items),
            ));

            return $template->load('items');
        });
    }
}
