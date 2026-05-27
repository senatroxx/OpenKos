<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $properties = Property::query()
            ->withCount([
                'rooms',
                'rooms as occupied_rooms_count' => fn (Builder $q) => $q
                    ->whereHas('leases', fn (Builder $q) => $q->where('status', 'active')),
                'rooms as maintenance_rooms_count' => fn (Builder $q) => $q
                    ->where('status', RoomStatus::Maintenance),
            ])
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalRooms = $properties->sum('rooms_count');
        $occupiedRooms = $properties->sum('occupied_rooms_count');
        $maintenanceRooms = $properties->sum('maintenance_rooms_count');

        return Inertia::render('dashboard', [
            'stats' => [
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'available_rooms' => $totalRooms - $occupiedRooms - $maintenanceRooms,
                'maintenance_rooms' => $maintenanceRooms,
                'occupancy_percentage' => $totalRooms > 0
                    ? round(($occupiedRooms / $totalRooms) * 100)
                    : 0,
                'properties' => $properties->map(fn (Property $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'total_rooms' => $p->rooms_count,
                    'occupied_rooms' => $p->occupied_rooms_count,
                    'available_rooms' => $p->rooms_count - $p->occupied_rooms_count - $p->maintenance_rooms_count,
                    'maintenance_rooms' => $p->maintenance_rooms_count,
                    'occupancy_percentage' => $p->rooms_count > 0
                        ? round(($p->occupied_rooms_count / $p->rooms_count) * 100)
                        : 0,
                ])->values()->toArray(),
            ],
        ]);
    }
}
