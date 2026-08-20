<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Models\MaintenanceTicket;
use App\Support\DateTimeFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTicketController extends TenantPortalController
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);
        $lease = $leaseContext['selectedLease'];
        $activeLease = $tenant->leases()->active()->with('unit.property')->first();

        $tickets = MaintenanceTicket::query()
            ->where('created_by', $request->user()->id)
            ->with(['property:id,name', 'unit:id,name'])
            ->latest()
            ->paginate(10)
            ->through(fn (MaintenanceTicket $ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'created_at' => DateTimeFormatter::format($ticket->created_at, 'Y-m-d'),
                'property_name' => $ticket->property?->name,
                'unit_name' => $ticket->unit?->name,
                'location' => $ticket->location,
            ]);

        return Inertia::render('tenant-portal/maintenance-tickets/index', [
            'tickets' => $tickets,
            'leaseContext' => $this->leaseContextPayload(
                $lease,
                $leaseContext['leases'],
            ),
            'activeLease' => $activeLease ? [
                'id' => $activeLease->id,
                'property_id' => $activeLease->unit->property_id,
                'property_name' => $activeLease->unit->property?->name,
                'unit_id' => $activeLease->unit_id,
                'unit_name' => $activeLease->unit->name,
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $lease = $tenant->leases()->active()->with('unit')->first();

        if (! $lease) {
            throw ValidationException::withMessages([
                'title' => __('You need an active lease to report maintenance.'),
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location_type' => ['required', 'string', 'in:unit,area'],
            'location' => ['nullable', 'required_if:location_type,area', 'string', 'max:255'],
        ]);

        $isUnit = $validated['location_type'] === 'unit';

        $ticket = MaintenanceTicket::create([
            'property_id' => $lease->unit->property_id,
            'unit_id' => $isUnit ? $lease->unit_id : null,
            'location' => $isUnit ? null : $validated['location'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => MaintenancePriority::Medium->value,
            'status' => MaintenanceStatus::Reported->value,
            'created_by' => $request->user()->id,
        ]);

        MaintenanceTicketCreated::dispatch($ticket, actorId: $request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Maintenance ticket submitted.'),
        ]);

        return back();
    }

    public function show(Request $request, MaintenanceTicket $ticket): Response
    {
        $tenant = $this->tenant($request);

        abort_unless($ticket->created_by === $request->user()->id, 403);

        $ticket->load(['property:id,name', 'unit:id,name', 'assignee:id,name']);

        $payload = [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'created_at' => DateTimeFormatter::format($ticket->created_at, 'Y-m-d'),
            'property_name' => $ticket->property?->name,
            'unit_name' => $ticket->unit?->name,
            'location' => $ticket->location,
            'assignee_name' => $ticket->assignee?->name,
            'resolution_notes' => $ticket->resolution_notes,
        ];

        return Inertia::render('tenant-portal/maintenance-tickets/show', [
            'ticket' => $payload,
        ]);
    }
}
