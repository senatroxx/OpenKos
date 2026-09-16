<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateInspection
{
    /**
     * @param  array{inspection_type: string, inspection_template_id: int, inspection_date: string, notes?: string|null, damage_observations?: string|null}  $attributes
     */
    public function execute(
        Property $property,
        ?Unit $unit,
        ?Lease $lease,
        array $attributes,
        User $inspector,
    ): Inspection {
        $type = InspectionType::from($attributes['inspection_type']);

        if ($type !== InspectionType::Periodic && $lease === null) {
            abort(422, __('Move-in and move-out inspections must be linked to a lease.'));
        }

        if ($unit !== null && $unit->property_id !== $property->id) {
            abort(404);
        }

        if ($lease !== null) {
            $lease->loadMissing('unit');

            abort_unless($lease->unit !== null, 422, __('The lease has no unit.'));
            abort_unless($lease->unit->property_id === $property->id, 422, __('The lease does not belong to this property.'));
            abort_unless($unit === null || $unit->id === $lease->unit_id, 422, __('The lease does not belong to this unit.'));

            $unit = $lease->unit;
        }

        return DB::transaction(function () use ($property, $unit, $lease, $attributes, $inspector): Inspection {
            $template = InspectionTemplate::query()
                ->whereKey($attributes['inspection_template_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            abort_unless(
                $template->inspection_type->value === $attributes['inspection_type'],
                422,
                __('The checklist template does not match the inspection type.'),
            );
            abort_if($template->items->isEmpty(), 422, __('The checklist template must contain at least one item.'));

            $inspection = Inspection::create([
                'inspection_template_id' => $template->id,
                'template_name' => $template->name,
                'property_id' => $property->id,
                'unit_id' => $unit?->id,
                'lease_id' => $lease?->id,
                'inspection_type' => $attributes['inspection_type'],
                'inspection_date' => $attributes['inspection_date'],
                'status' => InspectionStatus::Draft,
                'notes' => $attributes['notes'] ?? null,
                'damage_observations' => $attributes['damage_observations'] ?? null,
                'inspector_id' => $inspector->id,
            ]);

            $inspection->items()->createMany(
                $template->items->map(fn ($item): array => [
                    'label' => $item->label,
                    'description' => $item->description,
                    'position' => $item->position,
                ])->all(),
            );

            return $inspection->load('items');
        });
    }
}
