<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyLeasesController extends Controller
{
    public function __invoke(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);

        return Inertia::render('properties/leases', [
            'property' => $property,
        ]);
    }
}
