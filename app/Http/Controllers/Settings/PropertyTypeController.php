<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePropertyTypeRequest;
use App\Http\Requests\Settings\UpdatePropertyTypeRequest;
use App\Models\PropertyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PropertyTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/property-types', [
            'propertyTypes' => PropertyType::ordered()
                ->withCount('properties')
                ->get(['id', 'slug', 'label', 'is_active', 'sort_order']),
        ]);
    }

    public function store(StorePropertyTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        PropertyType::create([
            'slug' => $this->uniqueSlug($validated['label']),
            'label' => $validated['label'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (int) PropertyType::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property type added.')]);

        return back();
    }

    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType): RedirectResponse
    {
        // slug is immutable — only the label and active state are editable.
        $propertyType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property type updated.')]);

        return back();
    }

    public function destroy(PropertyType $propertyType): RedirectResponse
    {
        if ($propertyType->properties()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This type is in use. Deactivate it instead of deleting.')]);

            return to_route('settings.property-types.index');
        }

        $propertyType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property type deleted.')]);

        return to_route('settings.property-types.index');
    }

    private function uniqueSlug(string $label): string
    {
        $base = Str::slug($label, '_');
        $slug = $base;
        $counter = 1;

        while (PropertyType::where('slug', $slug)->exists()) {
            $slug = $base.'_'.++$counter;
        }

        return $slug;
    }
}
