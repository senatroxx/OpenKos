<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'floor', 'base_price', 'size_sqm', 'status', 'capacity'];
        $perPageOptions = [10, 15, 25, 50];

        if (! in_array($sort, $sortable)) {
            $sort = 'name';
        }

        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        if (! in_array($perPage, $perPageOptions)) {
            $perPage = 15;
        }

        $rooms = $property->rooms()
            ->withCount([
                'leases as active_leases' => fn (Builder $q) => $q->whereNull('end_date'),
            ])
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(floor)'), 'like', '%'.mb_strtolower($search).'%');
            }))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return Inertia::render('properties/rooms/index', [
            'property' => $property->only('id', 'name', 'slug', 'city'),
            'rooms' => $rooms,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function store(StoreRoomRequest $request, Property $property): RedirectResponse
    {
        $property->rooms()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room created.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function update(UpdateRoomRequest $request, Property $property, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room updated.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function destroy(Property $property, Room $room): RedirectResponse
    {
        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room deleted.')]);

        return to_route('properties.rooms.index', $property);
    }
}
