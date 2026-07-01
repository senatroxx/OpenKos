<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\OverviewStatsCalculator;
use App\Enums\RoomStatus;
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
                'rooms',
                'rooms as occupied_rooms_count' => fn (Builder $q) => $q
                    ->where(function (Builder $q) {
                        $q->where('status', RoomStatus::Occupied)
                            ->orWhereHas('leases', fn (Builder $q) => $q->where('status', 'active'));
                    }),
                'rooms as maintenance_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Maintenance)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active')),
                'rooms as unavailable_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Unavailable)
                    ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active')),
            ])
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalRooms = $properties->sum('rooms_count');
        $occupiedRooms = $properties->sum('occupied_rooms_count');
        $maintenanceRooms = $properties->sum('maintenance_rooms_count');
        $unavailableRooms = $properties->sum('unavailable_rooms_count');

        $accessibleProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->pluck('id');

        $activeLeases = Lease::where('status', 'active')
            ->whereHas('room.property', fn (Builder $q) => $q->whereIn('id', $accessibleProperties));

        $financeResult = $finance->computeFinance($activeLeases);

        return Inertia::render('dashboard/overview', [
            'finance' => $financeResult,
            'stats' => [
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'available_rooms' => $totalRooms - $occupiedRooms - $maintenanceRooms - $unavailableRooms,
                'maintenance_rooms' => $maintenanceRooms,
                'unavailable_rooms' => $unavailableRooms,
                'occupancy_percentage' => $totalRooms > 0
                    ? round(($occupiedRooms / $totalRooms) * 100)
                    : 0,
                'properties' => $properties->map(fn (Property $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'total_rooms' => $p->rooms_count,
                    'occupied_rooms' => $p->occupied_rooms_count,
                    'available_rooms' => $p->rooms_count - $p->occupied_rooms_count - $p->maintenance_rooms_count - $p->unavailable_rooms_count,
                    'maintenance_rooms' => $p->maintenance_rooms_count,
                    'unavailable_rooms' => $p->unavailable_rooms_count,
                    'occupancy_percentage' => $p->rooms_count > 0
                        ? round(($p->occupied_rooms_count / $p->rooms_count) * 100)
                        : 0,
                ])->values()->toArray(),
            ],
        ]);
    }
}
