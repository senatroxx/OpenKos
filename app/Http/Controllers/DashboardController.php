<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->withCount([
                'rooms',
                'rooms as occupied_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Occupied),
                'rooms as maintenance_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Maintenance),
                'rooms as unavailable_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Unavailable),
            ])
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalRooms = $properties->sum('rooms_count');
        $occupiedRooms = $properties->sum('occupied_rooms_count');
        $maintenanceRooms = $properties->sum('maintenance_rooms_count');
        $unavailableRooms = $properties->sum('unavailable_rooms_count');

        return Inertia::render('dashboard', [
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
