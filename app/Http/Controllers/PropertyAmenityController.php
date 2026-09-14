<?php

namespace App\Http\Controllers;

use App\Http\Requests\Amenity\SyncAmenitiesRequest;
use App\Models\Amenity;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PropertyAmenityController extends Controller
{
    public function syncProperty(SyncAmenitiesRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        $ids = array_map('intval', $request->validated('amenity_ids', []));
        $currentIds = $property->facilities()->pluck('amenities.id')->map(fn (mixed $id): int => (int) $id)->all();
        $newIds = array_values(array_diff($ids, $currentIds));

        $this->ensureNewAmenitiesAreActive($newIds);

        $property->facilities()->sync($ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property facilities updated.')]);

        return back();
    }

    /**
     * @param  array<int, int>  $newIds
     */
    private function ensureNewAmenitiesAreActive(array $newIds): void
    {
        if ($newIds === []) {
            return;
        }

        $amenities = Amenity::query()
            ->whereIn('id', $newIds)
            ->get(['id', 'is_active']);

        if ($amenities->count() !== count($newIds) || $amenities->contains(fn (Amenity $amenity): bool => ! $amenity->is_active)) {
            throw ValidationException::withMessages([
                'amenity_ids' => __('Inactive or unavailable amenities cannot be newly assigned.'),
            ]);
        }
    }
}
