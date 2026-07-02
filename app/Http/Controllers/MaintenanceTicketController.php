<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
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
            ->select(['id', 'name', 'property_id'])
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

        MaintenanceTicket::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket created.')]);

        return to_route('maintenance-tickets.index');
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

        $ticket->update($validated);

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
