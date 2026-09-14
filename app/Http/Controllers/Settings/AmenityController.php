<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAmenityRequest;
use App\Http\Requests\Settings\UpdateAmenityRequest;
use App\Models\Amenity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AmenityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/amenities', [
            'amenities' => Amenity::query()
                ->withCount(['properties', 'unitTypes'])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'icon', 'scope', 'is_active']),
        ]);
    }

    public function store(StoreAmenityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Amenity::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'icon' => $data['icon'] ?? null,
            'scope' => $data['scope'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity added.')]);

        return back();
    }

    public function update(UpdateAmenityRequest $request, Amenity $amenity): RedirectResponse
    {
        $amenity->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity updated.')]);

        return back();
    }

    public function destroy(Amenity $amenity): RedirectResponse
    {
        $archived = DB::transaction(function () use ($amenity): bool {
            $amenity = Amenity::query()->lockForUpdate()->findOrFail($amenity->id);

            if ($amenity->properties()->exists() || $amenity->unitTypes()->exists()) {
                $amenity->update(['is_active' => false]);

                return true;
            }

            $amenity->deleteOrFail();

            return false;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($archived ? 'Amenity archived.' : 'Amenity deleted.'),
        ]);

        return back();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'amenity';
        $slug = $base;
        $counter = 1;

        while (Amenity::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
