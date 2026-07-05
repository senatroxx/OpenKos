<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\OverviewStatsCalculator;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request, OverviewStatsCalculator $finance): Response
    {
        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->withCount([
                'units',
                'units as occupied_units_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->where('status', UnitStatus::Occupied)
                            ->orWhereHas('leases', fn (Builder $q) => $q->where('status', 'active'));
                    }),
                'units as maintenance_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Maintenance)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active')),
                'units as unavailable_units_count' => fn (Builder $q) => $q
                    ->where('status', UnitStatus::Unavailable)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active')),
            ])
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalUnits = $properties->sum('units_count');
        $occupiedUnits = $properties->sum('occupied_units_count');
        $maintenanceUnits = $properties->sum('maintenance_units_count');
        $unavailableUnits = $properties->sum('unavailable_units_count');

        $accessibleProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->pluck('id');

        $activeLeases = Lease::where('status', 'active')
            ->whereHas('unit.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties));

        $financeResult = $finance->computeFinance($activeLeases);

        return Inertia::render('dashboard/overview', [
            'finance' => $financeResult,
            'stats' => [
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'available_units' => $totalUnits - $occupiedUnits - $maintenanceUnits - $unavailableUnits,
                'maintenance_units' => $maintenanceUnits,
                'unavailable_units' => $unavailableUnits,
                'occupancy_percentage' => $totalUnits > 0
                    ? round(($occupiedUnits / $totalUnits) * 100)
                    : 0,
                'properties' => $properties->map(fn (Property $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'total_units' => $p->units_count,
                    'occupied_units' => $p->occupied_units_count,
                    'available_units' => $p->units_count - $p->occupied_units_count - $p->maintenance_units_count - $p->unavailable_units_count,
                    'maintenance_units' => $p->maintenance_units_count,
                    'unavailable_units' => $p->unavailable_units_count,
                    'occupancy_percentage' => $p->units_count > 0
                        ? round(($p->occupied_units_count / $p->units_count) * 100)
                        : 0,
                ])->values()->toArray(),
            ],
        ]);
    }
}
