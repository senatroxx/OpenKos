<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyRentalMode
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $property = $request->route('property');

        abort_unless($property instanceof Property, 404);

        Gate::authorize('view', $property);

        $isAvailable = match ($capability) {
            'unit_inventory' => $property->rental_mode->supportsUnitInventory(),
            'property_pricing' => $property->rental_mode->supportsPropertyPricing(),
            default => false,
        };

        abort_unless($isAvailable, 404);

        return $next($request);
    }
}
