<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
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
        $this->authorize('viewAny', [Room::class, $property]);

        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'floor', 'size_sqm', 'status', 'capacity'];
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
                'leases as active_leases' => fn (Builder $q) => $q->where('status', 'active'),
            ])
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']), 'activeRates'])
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(floor)'), 'like', '%'.mb_strtolower($search).'%');
            }))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        $tenantsList = Tenant::whereRaw('is_active is true')
            ->whereNull('deleted_at')
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'leases.room.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $availableRooms = $property->rooms()
            ->with('property.city')
            ->select(['id', 'name', 'property_id', 'capacity'])
            ->addSelect([
                'occupied_count' => DB::table('lease_tenant')
                    ->selectRaw('COALESCE(COUNT(*), 0)')
                    ->whereIn('lease_id', function (\Illuminate\Database\Query\Builder $q) {
                        $q->select('id')
                            ->from('leases')
                            ->whereColumn('room_id', 'rooms.id')
                            ->where('status', 'active');
                    }),
            ])
            ->whereNull('deleted_at')
            ->where(function (Builder $q) {
                $q->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
                    ->orWhereRaw('capacity > (SELECT COALESCE(COUNT(*), 0) FROM lease_tenant WHERE lease_id IN (SELECT id FROM leases WHERE room_id = rooms.id AND status = \'active\'))');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/rooms/index', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug, 'city' => $property->city?->name],
            'rooms' => $rooms,
            'tenants' => $tenantsList,
            'availableRooms' => $availableRooms,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function store(StoreRoomRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('create', [Room::class, $property]);

        $room = $property->rooms()->create($request->validated());

        if ($request->filled('rates')) {
            foreach ($request->rates as $rate) {
                $room->rates()->create([
                    'billing_interval' => $rate['billing_interval'],
                    'billing_unit' => $rate['billing_unit'],
                    'amount' => $rate['amount'],
                    'is_active' => true,
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room created.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function update(UpdateRoomRequest $request, Property $property, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $room->update($request->validated());

        if ($request->has('rates')) {
            $keepIds = [];
            foreach ($request->rates as $rate) {
                if (isset($rate['id'])) {
                    $room->rates()->where('id', $rate['id'])->update([
                        'billing_interval' => $rate['billing_interval'],
                        'billing_unit' => $rate['billing_unit'],
                        'amount' => $rate['amount'],
                        'is_active' => $rate['is_active'] ?? true,
                    ]);
                    $keepIds[] = $rate['id'];
                } else {
                    $new = $room->rates()->create([
                        'billing_interval' => $rate['billing_interval'],
                        'billing_unit' => $rate['billing_unit'],
                        'amount' => $rate['amount'],
                        'is_active' => true,
                    ]);
                    $keepIds[] = $new->id;
                }
            }
            $room->rates()->whereNotIn('id', $keepIds)->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room updated.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function destroy(Property $property, Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room deleted.')]);

        return to_route('properties.rooms.index', $property);
    }
}
