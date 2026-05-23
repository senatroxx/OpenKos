<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(): Response
    {
        $properties = Property::query()
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('properties/index', [
            'properties' => $properties,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('properties/create');
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $property = Property::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property created.')]);

        return to_route('properties.edit', $property);
    }

    public function edit(Property $property): Response
    {
        return Inertia::render('properties/edit', [
            'property' => $property,
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        $property->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property updated.')]);

        return to_route('properties.edit', $property);
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Property archived.')]);

        return to_route('properties.index');
    }
}
