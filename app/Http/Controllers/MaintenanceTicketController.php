<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\Maintenance\StoreMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTicketRequest;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
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
                Column::make('property_name', 'Property')->sortable(),
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
            ->with(['property:id,name', 'room:id,name', 'assignee.roles', 'creator.roles']);

        $propertyId = $request->query('property_id');
        if ($propertyId) {
            $query->where('property_id', (int) $propertyId);
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
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get();

        return Inertia::render('maintenance-tickets/index', [
            ...$result,
            'properties' => $properties,
            'rooms' => $rooms,
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

            $activeLease->load('tenants');

            $activeTenantsCount = DB::table('lease_tenant')
                ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
                ->where('leases.room_id', $targetRoom->id)
                ->where('leases.status', 'active')
                ->count();

            $incomingTenantIds = $activeLease->tenants->pluck('id')->toArray();
            $existingTargetLease = $targetRoom->leases()->where('status', 'active')->first();

            $incomingCount = $existingTargetLease
                ? count(array_diff($incomingTenantIds, $existingTargetLease->tenants()->pluck('tenants.id')->all()))
                : count($incomingTenantIds);

            abort_if(($activeTenantsCount + $incomingCount) > $targetRoom->capacity, 422, __('Target room capacity exceeded.'));

            $activeLease->update([
                'end_date' => now(),
                'status' => 'terminated',
                'termination_date' => now(),
                'termination_reason' => __('Moved to room :name (maintenance)', ['name' => $targetRoom->name]),
            ]);

            if ($existingTargetLease) {
                $existingTenantIds = $existingTargetLease->tenants()->pluck('tenants.id');
                foreach ($activeLease->tenants as $tenant) {
                    if (! $existingTenantIds->contains($tenant->id)) {
                        $existingTargetLease->tenants()->attach($tenant->id, ['is_primary' => DB::raw('false')]);
                    }
                }
            } else {
                $newLease = $targetRoom->leases()->create([
                    'primary_tenant_id' => $activeLease->primary_tenant_id,
                    'start_date' => now(),
                    'rent_amount' => $activeLease->rent_amount,
                    'billing_interval' => $activeLease->billing_interval ?? 1,
                    'billing_unit' => $activeLease->billing_unit ?? 'month',
                    'is_custom_price' => $activeLease->is_custom_price,
                    'deposit_amount' => $activeLease->deposit_amount,
                    'deposit_paid_at' => $activeLease->deposit_paid_at,
                    'rent_due_day' => $activeLease->rent_due_day,
                    'status' => 'active',
                    'notes' => __('Moved from room :name (maintenance)', ['name' => $room->name]),
                ]);

                foreach ($activeLease->tenants as $tenant) {
                    $newLease->tenants()->attach($tenant->id, [
                        'is_primary' => $tenant->id === $activeLease->primary_tenant_id ? DB::raw('true') : DB::raw('false'),
                    ]);
                }
            }

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
        unset($validated['restore_room']);

        $ticket->update($validated);

        if ($restoreRoom && $ticket->room_id) {
            $staysMaintenance = MaintenanceTicket::query()
                ->where('room_id', $ticket->room_id)
                ->whereKeyNot($ticket->id)
                ->whereNotIn('status', ['resolved', 'cancelled'])
                ->exists();

            if (! $staysMaintenance) {
                $room = Room::lockForUpdate()->findOrFail($ticket->room_id);
                $hasActiveLease = $room->leases()->where('status', 'active')->exists();
                $newRoomStatus = $hasActiveLease ? RoomStatus::Occupied : RoomStatus::Available;
                $room->update(['status' => $newRoomStatus]);

                Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated. Room :name restored.', ['name' => $room->name])]);

                return to_route('maintenance-tickets.index');
            } else {
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated. Room has other open tickets — not restored.')]);

                return to_route('maintenance-tickets.index');
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket updated.')]);

        return to_route('maintenance-tickets.index');
    }

    public function assign(Request $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((int) $validated['assigned_to'] !== $request->user()->id) {
            $this->authorize('assign', $ticket);
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
