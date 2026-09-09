<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitType\StoreUnitTypeRequest;
use App\Http\Requests\UnitType\UpdateUnitTypeRequest;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Property;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyUnitTypeController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);
        $unitTypes = $property->unitTypes()
            ->withCount('units')
            ->with(['amenities', 'media' => fn ($query) => $query->where('collection', 'photos')->orderBy('position')->orderBy('id')])
            ->orderBy('name')
            ->get();

        $unitTypes->each(function (UnitType $unitType) use ($property): void {
            $unitType->setAttribute('gallery', $this->gallery($unitType, $property));
            $unitType->unsetRelation('media');
        });

        $unitTypeAmenityIds = $unitTypes
            ->flatMap(fn (UnitType $unitType) => $unitType->amenities->modelKeys())
            ->unique()
            ->values()
            ->all();
        $amenities = Amenity::query()
            ->where(function ($query) use ($property, $unitTypeAmenityIds): void {
                $query->where(function ($query) use ($property): void {
                    $query->whereNull('owner_property_id')->where('is_active', true)
                        ->orWhere('owner_property_id', $property->id);
                })->when($unitTypeAmenityIds !== [], function ($query) use ($unitTypeAmenityIds): void {
                    $query->orWhereIn('id', $unitTypeAmenityIds);
                });
            })
            ->orderBy('name')
            ->get(['id', 'owner_property_id', 'name', 'is_active']);

        return Inertia::render('properties/unit-types/index', [
            'property' => $property,
            'unitTypes' => $unitTypes,
            'amenities' => $amenities,
        ]);
    }

    public function store(StoreUnitTypeRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('create', [UnitType::class, $property]);

        $validated = $request->validated();
        $amenityIds = $validated['amenity_ids'] ?? [];
        unset($validated['amenity_ids']);

        DB::transaction(function () use ($property, $validated, $amenityIds): void {
            $unitType = $property->unitTypes()->create($validated);
            $unitType->amenities()->sync($amenityIds);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('UnitType created.')]);

        return back();
    }

    public function update(UpdateUnitTypeRequest $request, Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $validated = $request->validated();
        $hasAmenities = array_key_exists('amenity_ids', $validated);
        $amenityIds = $validated['amenity_ids'] ?? [];
        unset($validated['amenity_ids']);

        DB::transaction(function () use ($unitType, $validated, $hasAmenities, $amenityIds): void {
            $unitType->update($validated);

            if ($hasAmenities) {
                $unitType->amenities()->sync($amenityIds);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('UnitType updated.')]);

        return back();
    }

    public function deactivate(Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $unitType->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('UnitType deactivated.')]);

        return back();
    }

    public function restore(Property $property, UnitType $unitType): RedirectResponse
    {
        $this->authorize('update', $unitType);
        abort_unless($unitType->property_id === $property->id, 404);

        $unitType->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('UnitType activated.')]);

        return back();
    }

    /**
     * @return array<int, array{id: int, url: string, position: int, alt: string|null, caption: string|null, original_name: string, mime_type: string}>
     */
    private function gallery(UnitType $unitType, Property $property): array
    {
        /** @var Collection<int, Media> $media */
        $media = $unitType->getRelation('media');

        return $media->map(function (Media $item) use ($property, $unitType): array {
            return [
                'id' => $item->id,
                'url' => route('properties.unit-types.gallery.show', [$property, $unitType, $item]),
                'position' => $item->position,
                'alt' => $item->metadata['alt'] ?? null,
                'caption' => $item->metadata['caption'] ?? null,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
            ];
        })->values()->all();
    }
}
