<?php

namespace App\Http\Controllers;

use App\Actions\Maintenance\BlockUnit;
use App\Actions\Maintenance\ResolveTicket;
use App\Business\Maintenance\TransitionValidator;
use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Events\Maintenance\MaintenanceResolved;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Unit\UnitStatusChanged;
use App\Http\Requests\Maintenance\StoreMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTicketRequest;
use App\Models\LeaseUnitHistory;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
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
    public function __construct(
        private TransitionValidator $transitionValidator,
        private BlockUnit $blockUnit,
        private ResolveTicket $resolveTicket,
    ) {}

    public function show(MaintenanceTicket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load(['property:id,name', 'unit:id,name,floor', 'assignee:id,name', 'creator:id,name']);

        return Inertia::render('maintenance-tickets/show', [
            'ticket' => $ticket,
        ]);
    }

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
            ->with(['property:id,name', 'unit:id,name', 'assignee.roles', 'creator.roles'])
            ->addSelect([
                'maintenance_transfer_to' => LeaseUnitHistory::query()
                    ->select('units.name')
                    ->join('units', 'lease_unit_histories.to_unit_id', '=', 'units.id')
                    ->whereColumn('lease_unit_histories.from_unit_id', 'maintenance_tickets.unit_id')
                    ->where('lease_unit_histories.reason', 'maintenance')
                    ->orderByDesc('lease_unit_histories.effective_date')
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

        $units = Unit::query()
            ->select(['id', 'slug', 'name', 'property_id', 'status'])
            ->withCount(['leases as active_lease_count' => fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)])
            ->with(['leases' => fn ($q) => $q->where('status', LeaseStatus::Active->value)->with('tenants:id,name')])
            ->addSelect([
                'has_maintenance_transfer' => LeaseUnitHistory::query()
                    ->selectRaw('1')
                    ->whereColumn('from_unit_id', 'units.id')
                    ->where('reason', 'maintenance')
                    ->limit(1),
            ])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get();

        $transfers = LeaseUnitHistory::query()
            ->with('toUnit:id,name')
            ->where('reason', 'maintenance')
            ->whereIn('from_unit_id', $units->pluck('id'))
            ->orderBy('effective_date', 'desc')
            ->get()
            ->keyBy('from_unit_id');

        foreach ($units as $unit) {
            $transfer = $transfers->get($unit->id);
            $unit->transfer_to_unit_name = $transfer?->toUnit?->name;
        }

        return Inertia::render('maintenance-tickets/index', [
            ...$result,
            'property_id' => $request->query('property_id'),
            'properties' => $properties,
            'units' => $units,
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

        $blockUnit = ! empty($data['block_unit']);
        $moveToUnitId = $data['move_tenant_to_unit_id'] ?? null;
        unset($data['block_unit'], $data['move_tenant_to_unit_id']);

        $ticket = MaintenanceTicket::create($data);

        MaintenanceTicketCreated::dispatch($ticket);

        if ($blockUnit && ! empty($data['unit_id'])) {
            $unit = Unit::findOrFail($data['unit_id']);
            $oldStatus = $unit->status;

            $targetUnit = $moveToUnitId ? Unit::find($moveToUnitId) : null;
            $oldTargetStatus = $targetUnit?->status;

            $this->blockUnit->execute($data['unit_id'], $moveToUnitId);

            $unit->refresh();
            if ($oldStatus !== $unit->status) {
                UnitStatusChanged::dispatch($unit, $oldStatus, $unit->status);
            }

            if ($targetUnit) {
                $targetUnit->refresh();
                if ($oldTargetStatus !== $targetUnit->status) {
                    UnitStatusChanged::dispatch($targetUnit, $oldTargetStatus, $targetUnit->status);
                }
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance ticket created.')]);

        return to_route('maintenance-tickets.index');
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validated();

        if (isset($validated['status'])) {
            $newStatus = MaintenanceStatus::from($validated['status']);
            $this->transitionValidator->validate($ticket->status, $newStatus);

            if ($newStatus === MaintenanceStatus::Resolved) {
                $validated['resolved_at'] ??= now();
            }

            $statusChangedToResolved = $newStatus === MaintenanceStatus::Resolved;
        }

        $restoreUnit = ! empty($validated['restore_unit']);
        $moveBack = ! empty($validated['move_back']);
        unset($validated['restore_unit'], $validated['move_back']);

        $ticket->update($validated);

        if (($statusChangedToResolved ?? false)) {
            MaintenanceResolved::dispatch($ticket);
        }

        if ($restoreUnit && $ticket->unit_id) {
            $unit = Unit::findOrFail($ticket->unit_id);
            $oldStatus = $unit->status;

            $transfer = LeaseUnitHistory::query()
                ->where('from_unit_id', $ticket->unit_id)
                ->where('reason', 'maintenance')
                ->orderBy('effective_date', 'desc')
                ->first();
            $targetUnit = $transfer ? Unit::find($transfer->to_unit_id) : null;
            $oldTargetStatus = $targetUnit?->status;

            $this->resolveTicket->execute($ticket, $moveBack);

            $unit->refresh();
            if ($oldStatus !== $unit->status) {
                UnitStatusChanged::dispatch($unit, $oldStatus, $unit->status);
            }

            if ($targetUnit) {
                $targetUnit->refresh();
                if ($oldTargetStatus !== $targetUnit->status) {
                    UnitStatusChanged::dispatch($targetUnit, $oldTargetStatus, $targetUnit->status);
                }
            }

            $unitName = $unit->name ?? '';
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated. Unit :name restored.', ['name' => $unitName])]);

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
}
