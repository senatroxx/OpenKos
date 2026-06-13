<?php

namespace App\Http\Controllers;

use App\Enums\BillingUnit;
use App\Enums\RoomStatus;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->query('sort', 'name');
        $direction = $request->query('direction', 'asc');
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $perPage = (int) $request->query('per_page', 15);

        $sortable = ['name', 'phone'];
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

        $assignedPropertyIds = ! $request->user()->isOwner()
            ? $request->user()->properties()->pluck('properties.id')
            : null;

        $tenants = Tenant::query()
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with(['room.property', 'tenants:id,name,phone', 'primaryTenant:id,name,phone'])])
            ->withCount(['leases as active_leases_count' => fn ($q) => $q->where('status', 'active')])
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereHas(
                'leases',
                fn (Builder $q) => $q->whereHas('room', fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds)),
            ))
            ->when($status === 'active', fn (Builder $q) => $q->whereRaw('is_active is true'))
            ->when($status === 'inactive', fn (Builder $q) => $q->whereRaw('is_active is false'))
            ->when($status === 'archived', fn (Builder $q) => $q->onlyTrashed())
            ->when(! $status || $status === 'active' || $status === 'inactive', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->when($search, fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(phone)'), 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere(DB::raw('lower(id_card_number)'), 'like', '%'.mb_strtolower($search).'%');
            }))
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        $availableRooms = Room::query()
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
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds))
            ->where(function (Builder $q) {
                $q->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
                    ->orWhereRaw('capacity > (SELECT COALESCE(COUNT(*), 0) FROM lease_tenant WHERE lease_id IN (SELECT id FROM leases WHERE room_id = rooms.id AND status = \'active\'))');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('tenants/index', [
            'tenants' => $tenants,
            'availableRooms' => $availableRooms,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ]);
    }

    public function assignRoom(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_ids' => ['nullable', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', 'distinct', 'exists:tenants,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['nullable', 'string', Rule::in(BillingUnit::values())],
            'room_rate_id' => ['nullable', 'integer', 'exists:room_rates,id'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid_at' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'between:1,31'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        $room = Room::findOrFail($validated['room_id']);

        $this->authorize('assignRoom', [Tenant::class, $room]);

        $tenantIds = $validated['tenant_ids'] !== null
            ? array_values(array_unique($validated['tenant_ids']))
            : [$tenant->id];

        $activeTenantsCount = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->where('leases.room_id', $room->id)
            ->where('leases.status', 'active')
            ->count();

        $totalOccupants = $activeTenantsCount + count($tenantIds);

        abort_if($totalOccupants > $room->capacity, 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));

        DB::transaction(function () use ($room, $tenantIds, $validated) {
            $existingLease = $room->leases()->where('status', 'active')->first();

            if ($existingLease) {
                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

                foreach ($tenantIds as $tenantId) {
                    if (! $existingTenantIds->contains($tenantId)) {
                        $existingLease->tenants()->attach($tenantId, ['is_primary' => DB::raw('false')]);
                    }
                }

                $room->update(['status' => RoomStatus::Occupied]);

                return;
            }

            $primaryTenantId = $tenantIds[0];

            $roomRate = isset($validated['room_rate_id']) ? RoomRate::find($validated['room_rate_id']) : null;
            $rentAmount = $validated['rent_amount'] ?? $roomRate?->amount ?? $room->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount');
            $isCustomPrice = isset($validated['rent_amount']) && $roomRate && (float) $validated['rent_amount'] !== (float) $roomRate->amount;

            $lease = $room->leases()->create([
                'primary_tenant_id' => $primaryTenantId,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'rent_amount' => $rentAmount,
                'billing_interval' => $validated['billing_interval'] ?? $roomRate?->billing_interval ?? 1,
                'billing_unit' => $validated['billing_unit'] ?? $roomRate?->billing_unit ?? 'month',
                'is_custom_price' => $isCustomPrice ? DB::raw('true') : DB::raw('false'),
                'room_rate_id' => $validated['room_rate_id'] ?? null,
                'deposit_amount' => $validated['deposit_amount'] ?? 0,
                'deposit_paid_at' => $validated['deposit_paid_at'] ?? null,
                'deposit_refund_amount' => null,
                'deposit_refunded_at' => null,
                'rent_due_day' => $validated['rent_due_day'] ?? 1,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($tenantIds as $index => $tid) {
                $lease->tenants()->attach($tid, ['is_primary' => $index === 0 ? DB::raw('true') : DB::raw('false')]);
            }

            $room->update(['status' => RoomStatus::Occupied]);
        });

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
