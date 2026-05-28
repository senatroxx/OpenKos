<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Property $property, Room $room): Response
    {
        abort_unless(request()->user()->canAccessProperty($property), 403);

        $room->load('property.city');

        $leases = $room->leases()
            ->with('tenant:id,name,phone')
            ->withTrashed()
            ->orderBy('created_at', 'desc')
            ->get()
            ->each->setRelation('room', $room);

        $availableRooms = $property->rooms()
            ->with('property.city')
            ->whereNull('deleted_at')
            ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'property_id']);

        return Inertia::render('properties/rooms/leases/index', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug, 'city' => $property->city?->name],
            'room' => $room->only('id', 'name', 'floor', 'base_price'),
            'leases' => $leases,
            'availableRooms' => $availableRooms,
        ]);
    }

    public function globalIndex(Request $request): Response
    {
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $properties = $request->query('properties', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['tenant_name', 'room_name', 'property_name', 'start_date', 'end_date', 'monthly_rent', 'status', 'created_at'];
        $perPageOptions = [10, 15, 25, 50];

        if (! in_array($sort, $sortable)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        if (! in_array($perPage, $perPageOptions)) {
            $perPage = 15;
        }

        $propertyIds = $properties
            ? array_map('intval', explode(',', $properties))
            : [];

        $leases = Lease::query()
            ->with(['tenant:id,name,phone', 'room:id,name,property_id', 'room.property:id,name'])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'room.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->whereHas('tenant', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                    ->orWhereHas('room', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                    ->orWhereHas('room.property', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'));
            }))
            ->when($status === 'active', fn (Builder $q) => $q->where('status', 'active'))
            ->when($status === 'terminated', fn (Builder $q) => $q->where('status', 'terminated'))
            ->when(! empty($propertyIds), fn (Builder $q) => $q->whereHas('room', fn (Builder $q) => $q->whereIn('property_id', $propertyIds)))
            ->orderBy('created_at', $direction)
            ->paginate($perPage);

        $leases->loadMissing('room.property.city');

        $availableRooms = Room::query()
            ->with('property.city')
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->whereNull('deleted_at')
            ->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'property_id']);

        $allProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('leases/index', [
            'leases' => $leases,
            'availableRooms' => $availableRooms,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'properties' => $allProperties,
        ]);
    }

    public function store(StoreLeaseRequest $request, Property $property, Room $room): RedirectResponse
    {
        abort_unless($request->user()->canAccessProperty($property), 403);

        $request->ensureRoomAvailable($room);

        $lease = $room->leases()->create([
            'tenant_id' => $request->tenant_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'monthly_rent' => $request->monthly_rent ?? $room->base_price,
            'deposit_amount' => $request->deposit_amount ?? 0,
            'deposit_paid_at' => $request->deposit_paid_at,
            'deposit_refund_amount' => $request->deposit_refund_amount,
            'deposit_refunded_at' => $request->deposit_refunded_at,
            'rent_due_day' => $request->rent_due_day ?? 1,
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        $lease->load('tenant:id,name,phone');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant assigned to room.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function update(UpdateLeaseRequest $request, Property $property, Room $room, Lease $lease): RedirectResponse
    {
        abort_unless($request->user()->canAccessProperty($property), 403);

        $lease->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease updated.')]);

        return to_route('properties.rooms.leases.index', [$property, $room]);
    }

    public function destroy(Property $property, Room $room, Lease $lease): RedirectResponse
    {
        abort_unless(request()->user()->canAccessProperty($property), 403);

        $lease->update([
            'end_date' => now(),
            'status' => 'terminated',
            'termination_date' => now(),
            'termination_reason' => request('reason'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease terminated.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function moveOut(Request $request, Lease $lease): RedirectResponse
    {
        abort_unless($request->user()->canAccessProperty($lease->room->property), 403);

        $validated = $request->validate([
            'move_out_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'deposit_returned' => ['nullable', 'boolean'],
            'deposit_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'move_to_another_room' => ['nullable', 'boolean'],
            'target_room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        if ($validated['move_to_another_room'] ?? false) {
            $targetRoom = Room::findOrFail($validated['target_room_id']);

            abort_unless($request->user()->canAccessProperty($targetRoom->property), 403);

            $hasActiveLease = Lease::query()
                ->where('room_id', $targetRoom->id)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveLease) {
                return back()->withErrors(['target_room_id' => __('Target room already has an active lease.')]);
            }
        }

        $depositRefundAmount = ($validated['deposit_returned'] ?? false)
            ? ($validated['deposit_refund_amount'] ?? $lease->deposit_amount)
            : null;

        $lease->update([
            'end_date' => $validated['move_out_date'],
            'status' => 'terminated',
            'termination_date' => now(),
            'termination_reason' => $validated['reason'],
            'deposit_refund_amount' => $depositRefundAmount,
            'deposit_refunded_at' => $validated['deposit_returned'] ? now() : null,
            'notes' => $validated['notes'] ?? $lease->notes,
        ]);

        if ($validated['move_to_another_room'] ?? false) {
            $targetRoom->leases()->create([
                'tenant_id' => $lease->tenant_id,
                'start_date' => $validated['move_out_date'],
                'monthly_rent' => $lease->monthly_rent ?? $targetRoom->base_price,
                'deposit_amount' => $lease->deposit_amount,
                'deposit_paid_at' => $lease->deposit_paid_at,
                'deposit_refund_amount' => null,
                'deposit_refunded_at' => null,
                'rent_due_day' => $lease->rent_due_day,
                'status' => 'active',
                'notes' => 'Moved from room '.$lease->room->name.' on '.now()->format('Y-m-d'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['move_to_another_room']
                ? __('Tenant moved to new room.')
                : __('Tenant moved out.'),
        ]);

        if ($validated['move_to_another_room']) {
            return to_route('properties.rooms.index', $lease->room->property_id);
        }

        return back();
    }

    public function move(Request $request, Property $property, Room $room, Lease $lease): RedirectResponse
    {
        abort_unless($request->user()->canAccessProperty($property), 403);

        $request->validate([
            'target_room_id' => ['required', 'integer', 'exists:rooms,id'],
        ]);

        $targetRoom = Room::where('id', $request->target_room_id)
            ->where('property_id', $property->id)
            ->firstOrFail();

        $hasActiveLease = Lease::query()
            ->where('room_id', $targetRoom->id)
            ->where('status', 'active')
            ->exists();

        abort_if($hasActiveLease, 422, __('Target room already has an active lease.'));

        $lease->update([
            'end_date' => now(),
            'status' => 'terminated',
            'termination_date' => now(),
            'termination_reason' => 'Moved to room '.$targetRoom->name,
            'notes' => ($lease->notes ? $lease->notes."\n" : '').'Moved to room '.$targetRoom->name.' on '.now()->format('Y-m-d'),
        ]);

        $targetRoom->leases()->create([
            'tenant_id' => $lease->tenant_id,
            'start_date' => now(),
            'monthly_rent' => $lease->monthly_rent ?? $targetRoom->base_price,
            'deposit_amount' => $lease->deposit_amount,
            'deposit_paid_at' => $lease->deposit_paid_at,
            'deposit_refund_amount' => $lease->deposit_refund_amount,
            'deposit_refunded_at' => $lease->deposit_refunded_at,
            'rent_due_day' => $lease->rent_due_day,
            'status' => 'active',
            'notes' => 'Moved from room '.$room->name.' on '.now()->format('Y-m-d'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant moved to new room.')]);

        return to_route('properties.rooms.index', $property);
    }
}
