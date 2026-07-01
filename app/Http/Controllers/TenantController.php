<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Data\Lease\CreateLeaseData;
use App\Http\Requests\Tenant\AssignRoomRequest;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Room;
use App\Models\Tenant;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(function (Builder $q, string $search): void {
                    $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                        ->orWhere(DB::raw('lower(phone)'), 'like', '%'.mb_strtolower($search).'%')
                        ->orWhere(DB::raw('lower(id_card_number)'), 'like', '%'.mb_strtolower($search).'%');
                }),
                Column::make('phone', 'Phone')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'inactive', 'archived'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'active' => $q->whereRaw('is_active is true'),
                        'inactive' => $q->whereRaw('is_active is false'),
                        'archived' => $q->onlyTrashed(),
                        default => $q,
                    }),
            ])
            ->defaultSort('name');

        $assignedPropertyIds = ! $request->user()->isOwner()
            ? $request->user()->properties()->pluck('properties.id')
            : null;

        $query = Tenant::query()
            ->with(['documents', 'leases' => fn ($q) => $q->where('status', 'active')->with(['room.property', 'tenants:id,name,phone', 'primaryTenant:id,name,phone'])])
            ->withCount(['leases as active_leases_count' => fn ($q) => $q->where('status', 'active')])
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereHas(
                'leases',
                fn (Builder $q) => $q->whereHas('room', fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds)),
            ));

        $result = $table->paginate($query, $request, 'tenants');

        $availableRooms = Room::query()
            ->with('property.city')
            ->select(['id', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds))
            ->orderBy('name')
            ->get();

        return Inertia::render('tenants/index', [
            ...$result,
            'availableRooms' => $availableRooms,
        ]);
    }

    public function assignRoom(AssignRoomRequest $request, Tenant $tenant, CreateLease $action): RedirectResponse
    {
        $validated = $request->validated();

        $room = Room::findOrFail($validated['room_id']);

        $this->authorize('assignRoom', [Tenant::class, $room]);

        $tenantIds = isset($validated['tenant_ids'])
            ? array_values(array_unique($validated['tenant_ids']))
            : [$tenant->id];

        $data = new CreateLeaseData(
            tenantIds: $tenantIds,
            startDate: $validated['start_date'],
            endDate: $validated['end_date'] ?? null,
            rentAmount: $validated['rent_amount'] ?? null,
            billingInterval: $validated['billing_interval'] ?? null,
            billingUnit: $validated['billing_unit'] ?? null,
            roomRateId: $validated['room_rate_id'] ?? null,
            depositAmount: $validated['deposit_amount'] ?? null,
            depositPaidAt: $validated['deposit_paid_at'] ?? null,
            depositRefundAmount: null,
            depositRefundedAt: null,
            rentDueDay: $validated['rent_due_day'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $action->execute($room, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant assigned to room.')]);

        return to_route('tenants.index');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant created.')]);

        return to_route('tenants.index');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant updated.')]);

        return to_route('tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant archived.')]);

        return to_route('tenants.index');
    }
}
