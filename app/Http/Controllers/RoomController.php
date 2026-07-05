<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function show(Property $property, Room $room): Response
    {
        $this->authorize('view', $room);

        $room->load(['property.city', 'activeRates'])
            ->loadCount(['leases as active_leases' => fn (Builder $q) => $q->where('status', 'active')])
            ->load(['leases' => fn ($q) => $q->where('status', 'active')
                ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            ]);

        return Inertia::render('properties/rooms/show', [
            'property' => $property,
            'room' => $room,
        ]);
    }

    public function leaseHistory(Request $request, Property $property, Room $room): Response
    {
        $this->authorize('view', $room);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(leases.reference)'), 'like', $s)
                        ->orWhereHas('tenants', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', $s));
                }),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('rent_amount', 'Rent')->sortable(),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'terminated'])
                    ->query(fn (Builder $q, string $value) => $q->where('leases.status', $value)),
            ])
            ->defaultSort('-start_date');

        $result = $table->paginate(
            $room->leases()->withTrashed()->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            $request,
            'leases',
        );

        return Inertia::render('properties/rooms/lease-history', [
            ...$result,
            'property' => $property->only('id', 'slug', 'name'),
            'room' => $room->only('id', 'slug', 'name', 'floor'),
        ]);
    }

    public function index(Request $request, Property $property): Response|JsonResponse
    {
        $this->authorize('viewAny', [Room::class, $property]);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);

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

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        $tenantsList = Tenant::where('is_active', true)
            ->whereNull('deleted_at')
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'leases.room.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $availableRooms = $property->rooms()
            ->with('property.city')
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/rooms/index', [
            ...$result,
            'property' => $property,
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

    public function maintenanceHistory(Request $request, Property $property, Room $room): Response
    {
        $this->authorize('viewAny', MaintenanceTicket::class);

        $table = Table::make()
            ->columns([
                Column::make('title', 'Title')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(title)'), 'like', $s)
                        ->orWhere(DB::raw('lower(reference)'), 'like', $s);
                }),
                Column::make('priority', 'Priority')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('cost', 'Cost')->sortable(),
                Column::make('created_at', 'Created')->sortable(),
                Column::make('resolved_at', 'Resolved')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', array_map(fn (MaintenanceStatus $s) => $s->value, MaintenanceStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('priority', 'Priority', ['low', 'medium', 'high', 'urgent'])
                    ->query(fn (Builder $q, string $value) => $q->where('priority', $value)),
            ])
            ->defaultSort('-created_at');

        $result = $table->paginate(
            $room->maintenanceTickets()->with(['property:id,name', 'assignee:id,name', 'creator:id,name']),
            $request,
            'tickets',
        );

        return Inertia::render('properties/rooms/maintenance-history', [
            ...$result,
            'property' => $property->only('id', 'slug', 'name'),
            'room' => $room->only('id', 'slug', 'name', 'floor'),
        ]);
    }
}
