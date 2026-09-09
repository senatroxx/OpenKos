<?php

namespace App\Http\Controllers;

use App\Http\Requests\Amenity\StoreAmenityRequest;
use App\Http\Requests\Amenity\SyncAmenitiesRequest;
use App\Models\Amenity;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PropertyAmenityController extends Controller
{
    public function store(StoreAmenityRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        Amenity::create([
            'owner_property_id' => $property->id,
            'name' => $request->validated('name'),
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom amenity created.')]);

        return back();
    }

    public function syncProperty(SyncAmenitiesRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $ids = array_map('intval', $request->validated('amenity_ids', []));
        $currentIds = $property->facilities()->pluck('amenities.id')->map(fn (mixed $id): int => (int) $id)->all();
        $newIds = array_values(array_diff($ids, $currentIds));

        $this->ensureNewAmenitiesAreActive($newIds, $property);

        $property->facilities()->sync($ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property facilities updated.')]);

        return back();
    }

    public function deactivate(Property $property, int $amenity): RedirectResponse
    {
        $this->authorize('update', $property);
        $amenity = Amenity::query()->findOrFail($amenity);
        abort_unless($amenity->owner_property_id === $property->id, 404);

        $amenity->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity deactivated.')]);

        return back();
    }

    public function restore(Property $property, int $amenity): RedirectResponse
    {
        $this->authorize('update', $property);
        $amenity = Amenity::query()->findOrFail($amenity);
        abort_unless($amenity->owner_property_id === $property->id, 404);

        $amenity->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity activated.')]);

        return back();
    }

    /**
     * @param  array<int, int>  $newIds
     */
    private function ensureNewAmenitiesAreActive(array $newIds, Property $property): void
    {
        if ($newIds === []) {
            return;
        }

        $amenities = Amenity::query()
            ->whereIn('id', $newIds)
            ->where(function ($query) use ($property): void {
                $query->whereNull('owner_property_id')->orWhere('owner_property_id', $property->id);
            })
            ->get(['id', 'is_active']);

        if ($amenities->count() !== count($newIds) || $amenities->contains(fn (Amenity $amenity): bool => ! $amenity->is_active)) {
            throw ValidationException::withMessages([
                'amenity_ids' => __('Inactive or unavailable amenities cannot be newly assigned.'),
            ]);
        }
    }
}
