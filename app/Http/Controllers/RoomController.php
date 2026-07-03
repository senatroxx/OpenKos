<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $this->authorize('viewAny', [Room::class, $property]);

        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(),
                Column::make('floor', 'Floor')->sortable()->searchable(),
                Column::make('size_sqm', 'Size')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('capacity', 'Capacity')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['available', 'occupied', 'maintenance', 'unavailable'])
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
            ])
            ->defaultSort('name');

        $query = $property->rooms()
            ->withCount([
                'leases as active_leases' => fn (Builder $q) => $q->where('status', 'active'),
            ])
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']), 'activeRates']);

        $result = $table->paginate($query, $request, 'rooms');

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
            ->withOccupiedCount()
            ->availableForAssignment()
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/rooms/index', [
            ...$result,
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug, 'city' => $property->city?->name],
            'tenants' => $tenantsList,
            'availableRooms' => $availableRooms,
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
