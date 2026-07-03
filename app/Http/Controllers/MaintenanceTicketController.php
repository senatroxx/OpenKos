<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\Maintenance\StoreMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTicketRequest;
use App\Models\Lease;
use App\Models\LeaseRoomHistory;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MaintenanceTicket::class);

        $table = Table::make()
            ->columns([
                Column::make('title', 'Title')->sortable()->searchable(),
                Column::make('property_name', 'Property')->sortable(function (Builder $q, string $direction) {
                    $q->leftJoin('properties', 'maintenance_tickets.property_id', '=', 'properties.id')
                        ->orderBy('properties.name', $direction);
                }),
                Column::make('priority', 'Priority')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('created_at', 'Created')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', array_map(fn (MaintenanceStatus $s) => $s->value, MaintenanceStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('priority', 'Priority', ['low', 'medium', 'high', 'urgent'])
                    ->query(fn (Builder $q, string $value) => $q->where('priority', $value)),
            ])
            ->defaultSort('-created_at');

        $query = MaintenanceTicket::query()
            ->with(['property:id,name', 'room:id,name', 'assignee.roles', 'creator.roles'])
            ->addSelect([
                'maintenance_transfer_to' => LeaseRoomHistory::query()
                    ->select('rooms.name')
                    ->join('rooms', 'lease_room_histories.to_room_id', '=', 'rooms.id')
                    ->whereColumn('lease_room_histories.from_room_id', 'maintenance_tickets.room_id')
                    ->where('lease_room_histories.reason', 'maintenance')
                    ->orderByDesc('lease_room_histories.effective_date')
                    ->limit(1),
            ]);

        $propertyId = $request->query('property_id');
        if ($propertyId) {
            $query->where('property_id', (int) $propertyId);
        }

        if (! $request->user()->isOwner()) {
            $query->whereHas('property.users', fn (Builder $q) => $q->whereKey($request->user()->id));
        }

        $result = $table->paginate($query, $request, 'tickets');

        $properties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rooms = Room::query()
            ->select(['id', 'name', 'property_id', 'status'])
            ->withCount(['leases as active_lease_count' => fn (Builder $q) => $q->where('status', 'active')])
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with('tenants:id,name')])
            ->addSelect([
                'has_maintenance_transfer' => LeaseRoomHistory::query()
                    ->selectRaw('1')
                    ->whereColumn('from_room_id', 'rooms.id')
                    ->where('reason', 'maintenance')
                    ->limit(1),
            ])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get();

        $transfers = LeaseRoomHistory::query()
            ->with('toRoom:id,name')
            ->where('reason', 'maintenance')
            ->whereIn('from_room_id', $rooms->pluck('id'))
            ->orderBy('effective_date', 'desc')
            ->get()
            ->keyBy('from_room_id');

        foreach ($rooms as $room) {
            $transfer = $transfers->get($room->id);
            $room->transfer_to_room_name = $transfer?->toRoom?->name;
        }

        return Inertia::render('maintenance-tickets/index', [
            ...$result,
            'properties' => $properties,
            'rooms' => $rooms,
            'users' => User::query()
                ->with('roles')
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => [
                'create' => $request->user()->can('maintenance-tickets.create'),
                'update' => $request->user()->can('maintenance-tickets.update'),
                'delete' => $request->user()->can('maintenance-tickets.delete'),
                'assign' => $request->user()->can('maintenance-tickets.assign'),
            ],
        ]);
    }

    public function store(StoreMaintenanceTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', MaintenanceTicket::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['status'] = MaintenanceStatus::Reported->value;

        $blockRoom = ! empty($data['block_room']);
        $moveToRoomId = $data['move_tenant_to_room_id'] ?? null;
        unset($data['block_room'], $data['move_tenant_to_room_id']);

        MaintenanceTicket::create($data);

        if ($blockRoom && ! empty($data['room_id'])) {
            $this->blockRoom($data['room_id'], $moveToRoomId);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket created.')]);

        return to_route('maintenance-tickets.index');
    }

    private function blockRoom(int $roomId, ?int $moveToRoomId): void
    {
        $room = Room::lockForUpdate()->findOrFail($roomId);
        $activeLease = $room->leases()->where('status', 'active')->first();

        if ($activeLease && $moveToRoomId) {
            $targetRoom = Room::lockForUpdate()->findOrFail($moveToRoomId);

            abort_if($targetRoom->status === RoomStatus::Maintenance, 422, __('Target room is under maintenance.'));
            abort_if($targetRoom->id === $room->id, 422, __('Cannot move to the same room.'));

            $targetHasLease = $targetRoom->leases()->where('status', 'active')->exists();
            abort_if($targetHasLease, 422, __('Target room already has an active lease.'));

            $activeLease->load('tenants');

            $activeTenantsCount = DB::table('lease_tenant')
                ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
                ->where('leases.room_id', $targetRoom->id)
                ->where('leases.status', 'active')
                ->count();

            $incomingCount = $activeLease->tenants->count();

            abort_if(($activeTenantsCount + $incomingCount) > $targetRoom->capacity, 422, __('Target room capacity exceeded.'));

            LeaseRoomHistory::create([
                'lease_id' => $activeLease->id,
                'from_room_id' => $room->id,
                'to_room_id' => $targetRoom->id,
                'transferred_by' => auth()->id(),
                'reason' => 'maintenance',
                'notes' => __('Room :from blocked for maintenance. Transfer to :to.', ['from' => $room->name, 'to' => $targetRoom->name]),
                'effective_date' => now(),
            ]);

            $notes = $activeLease->notes
                ? $activeLease->notes."\n".__('Transferred from :from to :to (maintenance)', ['from' => $room->name, 'to' => $targetRoom->name])
                : __('Transferred from :from to :to (maintenance)', ['from' => $room->name, 'to' => $targetRoom->name]);

            $activeLease->update([
                'room_id' => $targetRoom->id,
                'notes' => $notes,
            ]);

            $targetRoom->update(['status' => RoomStatus::Occupied]);
            $room->update(['status' => RoomStatus::Maintenance]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant moved to :room. Ticket created.', ['name' => $targetRoom->name])]);
        } elseif ($activeLease) {
            $room->update(['status' => RoomStatus::Maintenance]);

            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Ticket created. Room :name is blocked but still has an active lease.', ['name' => $room->name])]);
        } else {
            $room->update(['status' => RoomStatus::Maintenance]);
        }
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validated();

        if (isset($validated['status'])) {
            $newStatus = MaintenanceStatus::from($validated['status']);
            $this->validateTransition($ticket->status, $newStatus);

            if ($newStatus === MaintenanceStatus::Resolved) {
                $validated['resolved_at'] ??= now();
            }
        }

        $restoreRoom = ! empty($validated['restore_room']);
        $moveBack = ! empty($validated['move_back']);
        unset($validated['restore_room'], $validated['move_back']);

        $ticket->update($validated);

        if ($restoreRoom && $ticket->room_id) {
            $staysMaintenance = MaintenanceTicket::query()
                ->where('room_id', $ticket->room_id)
                ->whereKeyNot($ticket->id)
                ->whereNotIn('status', ['resolved', 'cancelled'])
                ->exists();

            if ($staysMaintenance) {
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated. Room has other open tickets — not restored.')]);

                return to_route('maintenance-tickets.index');
            }

            $room = Room::lockForUpdate()->findOrFail($ticket->room_id);

            if ($moveBack) {
                $transfer = LeaseRoomHistory::query()
                    ->where('from_room_id', $ticket->room_id)
                    ->where('reason', 'maintenance')
                    ->orderBy('effective_date', 'desc')
                    ->first();

                if ($transfer) {
                    $movedLease = Lease::where('status', 'active')
                        ->where('room_id', $transfer->to_room_id)
                        ->first();

                    if ($movedLease) {
                        $targetHasLease = $room->leases()
                            ->where('status', 'active')
                            ->whereKeyNot($movedLease->id)
                            ->exists();

                        abort_if($targetHasLease, 422, __('Original room now has an active lease. Cannot move back.'));

                        LeaseRoomHistory::create([
                            'lease_id' => $movedLease->id,
                            'from_room_id' => $transfer->to_room_id,
                            'to_room_id' => $room->id,
                            'transferred_by' => auth()->id(),
                            'reason' => 'maintenance_resolved',
                            'notes' => __('Ticket :ref resolved. Transfer back to :room.', ['ref' => $ticket->reference, 'room' => $room->name]),
                            'effective_date' => now(),
                        ]);

                        $notes = $movedLease->notes
                            ? $movedLease->notes."\n".__('Transferred back to :room (maintenance resolved)', ['room' => $room->name])
                            : __('Transferred back to :room (maintenance resolved)', ['room' => $room->name]);

                        $movedLease->update([
                            'room_id' => $room->id,
                            'notes' => $notes,
                        ]);

                        $targetRoom = Room::lockForUpdate()->findOrFail($transfer->to_room_id);
                        $targetRoomStillOccupied = $targetRoom->leases()->where('status', 'active')->exists();
                        $targetRoom->update(['status' => $targetRoomStillOccupied ? RoomStatus::Occupied : RoomStatus::Available]);
                    }
                }
            }

            $hasActiveLease = $room->leases()->where('status', 'active')->exists();
            $newRoomStatus = $hasActiveLease ? RoomStatus::Occupied : RoomStatus::Available;
            $room->update(['status' => $newRoomStatus]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated. Room :name restored.', ['name' => $room->name])]);

            return to_route('maintenance-tickets.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket updated.')]);

        return to_route('maintenance-tickets.index');
    }

    public function assign(Request $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $isSelfAssign = (int) $validated['assigned_to'] === $request->user()->id;

        if ($isSelfAssign) {
            $this->authorize('update', $ticket);
            abort_unless($request->user()->can('maintenance-tickets.update'), 403, __('You do not have permission to self-assign tickets.'));
        } else {
            $this->authorize('assign', $ticket);
            abort_unless($request->user()->can('maintenance-tickets.assign'), 403, __('You do not have permission to assign tickets to others.'));
        }

        $ticket->update(['assigned_to' => $validated['assigned_to']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket assigned.')]);

        return to_route('maintenance-tickets.index');
    }

    public function destroy(MaintenanceTicket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket deleted.')]);

        return to_route('maintenance-tickets.index');
    }

    private function validateTransition(MaintenanceStatus $current, MaintenanceStatus $next): void
    {
        $allowed = match ($current) {
            MaintenanceStatus::Reported => [MaintenanceStatus::InProgress, MaintenanceStatus::Cancelled],
            MaintenanceStatus::InProgress => [MaintenanceStatus::Resolved, MaintenanceStatus::Cancelled],
            default => [],
        };

        abort_unless(in_array($next, $allowed), 422, __('Cannot transition from :current to :next.', [
            'current' => $current->label(),
            'next' => $next->label(),
        ]));
    }
}
