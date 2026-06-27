<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Http\Requests\Lease\MoveLeaseRequest;
use App\Http\Requests\Lease\MoveOutRequest;
use App\Http\Requests\Lease\StoreLeaseRequest;
use App\Http\Requests\Lease\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomRate;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
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
        $this->authorize('viewAny', [Lease::class, $property]);

        $room->load('property.city');

        $leases = $room->leases()
            ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone', 'payments.confirmedBy:id,name'])
            ->withTrashed()
            ->orderBy('created_at', 'desc')
            ->get()
            ->each->setRelation('room', $room);

        $availableRooms = $property->rooms()
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
            ->where(function (Builder $q) {
                $q->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
                    ->orWhereRaw('capacity > (SELECT COALESCE(COUNT(*), 0) FROM lease_tenant WHERE lease_id IN (SELECT id FROM leases WHERE room_id = rooms.id AND status = \'active\'))');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/rooms/leases/index', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug, 'city' => $property->city?->name],
            'room' => $room->only('id', 'name', 'floor'),
            'leases' => $leases,
            'availableRooms' => $availableRooms,
        ]);
    }

    public function globalIndex(Request $request): Response
    {
        $allProperties = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name']);

        $table = Table::make()
            ->columns([
                Column::make('tenant_name', 'Tenant')->searchable(function (Builder $q, string $search): void {
                    $q->whereHas('tenants', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                        ->orWhereHas('room', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                        ->orWhereHas('room.property', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'));
                }),
                Column::make('room_name', 'Room'),
                Column::make('property_name', 'Property'),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('rent_amount', 'Rent')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('created_at', 'Created')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'terminated'])
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('payment_status', 'Payment', [
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                ])
                    ->query(fn (Builder $q, string $value) => $value === 'paid'
                        ? $q->whereHas('payments', fn (Builder $q) => $q
                            ->where('paymentable_type', Lease::class)
                            ->whereNotIn('status', ['cancelled'])
                            ->whereMonth('period_start', now()->month)
                            ->whereYear('period_start', now()->year))
                        : $q->where('status', 'active')->whereDoesntHave('payments', fn (Builder $q) => $q
                            ->where('paymentable_type', Lease::class)
                            ->whereNotIn('status', ['cancelled'])
                            ->whereMonth('period_start', now()->month)
                            ->whereYear('period_start', now()->year))),
                Filter::select('properties', 'Property', $allProperties->map(fn (Property $p) => [
                    'value' => (string) $p->id,
                    'label' => $p->name,
                ])->all())
                    ->query(fn (Builder $q, string $value) => $q->whereHas(
                        'room',
                        fn (Builder $q) => $q->whereIn('property_id', explode(',', $value)),
                    )),
            ])
            ->defaultSort('status,-start_date');

        $query = Lease::query()
            ->with(['primaryTenant:id,name,phone', 'tenants:id,name,phone', 'room:id,name,property_id', 'room.property:id,name'])
            ->addSelect(['payment_status' => Payment::query()
                ->selectRaw("CASE WHEN COUNT(*) > 0 THEN 'paid' ELSE 'overdue' END")
                ->whereColumn('paymentable_id', 'leases.id')
                ->where('paymentable_type', Lease::class)
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('period_start', now()->month)
                ->whereYear('period_start', now()->year),
            ])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'room.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ));

        $result = $table->paginate($query, $request, 'leases');

        $leases = $result['leases'];
        $leases->loadMissing(['room.property.city', 'payments.confirmedBy:id,name']);

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
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->whereNull('deleted_at')
            ->where(function (Builder $q) {
                $q->whereDoesntHave('leases', fn (Builder $q) => $q->where('status', 'active'))
                    ->orWhereRaw('capacity > (SELECT COALESCE(COUNT(*), 0) FROM lease_tenant WHERE lease_id IN (SELECT id FROM leases WHERE room_id = rooms.id AND status = \'active\'))');
            })
            ->orderBy('name')
            ->get();

        $accessibleQuery = fn (Builder $q) => $request->user()->isOwner()
            ? $q
            : $q->whereHas('room.property.users', fn (Builder $q) => $q->whereKey($request->user()->id));

        $activeLeases = Lease::query()
            ->where('status', 'active')
            ->when($accessibleQuery)
            ->count();

        $collectedThisMonth = (float) Payment::query()
            ->where('paymentable_type', Lease::class)
            ->whereNotIn('status', ['cancelled'])
            ->whereMonth('period_start', now()->month)
            ->whereYear('period_start', now()->year)
            ->whereHasMorph('paymentable', [Lease::class], fn (Builder $q) => $q->where('status', 'active')->when($accessibleQuery))
            ->sum('amount');

        $overdueAmount = (float) Lease::query()
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->whereDoesntHave('payments', fn (Builder $q) => $q
                ->where('paymentable_type', Lease::class)
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('period_start', now()->month)
                ->whereYear('period_start', now()->year))
            ->when($accessibleQuery)
            ->sum('rent_amount');

        return Inertia::render('leases/index', [
            ...$result,
            'availableRooms' => $availableRooms,
            'stats' => [
                'active_leases' => $activeLeases,
                'collected_this_month' => $collectedThisMonth,
                'overdue_amount' => $overdueAmount,
            ],
        ]);
    }

    public function store(StoreLeaseRequest $request, Property $property, Room $room): RedirectResponse
    {
        $this->authorize('create', [Lease::class, $property]);

        $tenantIds = array_values(array_unique($request->tenant_ids));

        $lease = DB::transaction(function () use ($room, $request, $tenantIds) {
            $room = Room::lockForUpdate()->findOrFail($room->id);

            $existingLease = $room->leases()->where('status', 'active')->first();

            $activeTenantsCount = DB::table('lease_tenant')
                ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
                ->where('leases.room_id', $room->id)
                ->where('leases.status', 'active')
                ->count();

            if ($existingLease) {
                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');
                $newTenantIds = array_diff($tenantIds, $existingTenantIds->all());

                abort_if(($activeTenantsCount + count($newTenantIds)) > $room->capacity, 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));

                foreach ($newTenantIds as $tenantId) {
                    $existingLease->tenants()->attach($tenantId, ['is_primary' => DB::raw('false')]);
                }

                $room->update(['status' => RoomStatus::Occupied]);

                return $existingLease;
            }

            abort_if(($activeTenantsCount + count($tenantIds)) > $room->capacity, 422, __('Room capacity exceeded. Room can only hold :capacity occupants.', ['capacity' => $room->capacity]));

            $roomRate = $request->room_rate_id ? RoomRate::find($request->room_rate_id) : null;
            $rentAmount = $request->rent_amount ?? $roomRate?->amount ?? $room->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount');
            $isCustomPrice = $request->rent_amount !== null && $roomRate && (float) $request->rent_amount !== (float) $roomRate->amount;

            $primaryTenantId = $tenantIds[0];

            $lease = $room->leases()->create([
                'primary_tenant_id' => $primaryTenantId,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'rent_amount' => $rentAmount,
                'billing_interval' => $request->billing_interval ?? $roomRate?->billing_interval ?? 1,
                'billing_unit' => $request->billing_unit ?? $roomRate?->billing_unit ?? 'month',
                'is_custom_price' => $isCustomPrice ? DB::raw('true') : DB::raw('false'),
                'room_rate_id' => $request->room_rate_id,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'deposit_paid_at' => $request->deposit_paid_at,
                'deposit_refund_amount' => $request->deposit_refund_amount,
                'deposit_refunded_at' => $request->deposit_refunded_at,
                'rent_due_day' => $request->rent_due_day ?? 1,
                'status' => 'active',
                'notes' => $request->notes,
            ]);

            foreach ($tenantIds as $index => $tenantId) {
                $lease->tenants()->attach($tenantId, ['is_primary' => $index === 0 ? DB::raw('true') : DB::raw('false')]);
            }

            $room->update(['status' => RoomStatus::Occupied]);

            return $lease;
        });

        $lease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

        $count = $lease->tenants->count();
        $message = $count > 1
            ? __(':count tenants assigned to room.', ['count' => $count])
            : __('Tenant assigned to room.');

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('properties.rooms.index', $property);
    }

    public function update(UpdateLeaseRequest $request, Property $property, Room $room, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);

        $lease->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease updated.')]);

        return to_route('properties.rooms.leases.index', [$property, $room]);
    }

    public function destroy(Property $property, Room $room, Lease $lease): RedirectResponse
    {
        $this->authorize('delete', $lease);

        DB::transaction(function () use ($lease, $room) {
            $lease->update([
                'end_date' => now(),
                'status' => 'terminated',
                'termination_date' => now(),
                'termination_reason' => request('reason'),
            ]);

            $room->unsetRelation('leases');

            if ($room->leases()->where('status', 'active')->doesntExist()) {
                $room->update(['status' => RoomStatus::Available]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease terminated.')]);

        return to_route('properties.rooms.index', $property);
    }

    public function moveOut(MoveOutRequest $request, Lease $lease): RedirectResponse
    {
        $validated = $request->validated();

        $targetRoom = ($validated['move_to_another_room'] ?? false)
            ? Room::findOrFail($validated['target_room_id'])
            : null;

        $this->authorize('moveOut', [$lease, $targetRoom]);

        $depositRefundAmount = ($validated['deposit_returned'] ?? false)
            ? ($validated['deposit_refund_amount'] ?? $lease->deposit_amount)
            : null;

        DB::transaction(function () use ($lease, $validated, $targetRoom, $depositRefundAmount) {
            if ($validated['move_to_another_room'] ?? false) {
                $targetRoom = Room::lockForUpdate()->findOrFail($targetRoom->id);

                $lease->load('tenants');

                $activeTenantsCount = DB::table('lease_tenant')
                    ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
                    ->where('leases.room_id', $targetRoom->id)
                    ->where('leases.status', 'active')
                    ->count();

                $existingLease = $targetRoom->leases()->where('status', 'active')->first();

                $incomingTenantIds = $lease->tenants->pluck('id')->toArray();

                $incomingCount = $existingLease
                    ? count(array_diff($incomingTenantIds, $existingLease->tenants()->pluck('tenants.id')->all()))
                    : count($incomingTenantIds);

                abort_if(($activeTenantsCount + $incomingCount) > $targetRoom->capacity, 422, __('Room capacity exceeded. Target room can only hold :capacity occupants.', ['capacity' => $targetRoom->capacity]));
            }
            $oldRoom = $lease->room;

            $lease->update([
                'end_date' => $validated['move_out_date'],
                'status' => 'terminated',
                'termination_date' => now(),
                'termination_reason' => $validated['reason'],
                'deposit_refund_amount' => $depositRefundAmount,
                'deposit_refunded_at' => $validated['deposit_returned'] ? now() : null,
                'notes' => $validated['notes'] ?? $lease->notes,
            ]);

            $oldRoom->unsetRelation('leases');

            if ($oldRoom->leases()->where('status', 'active')->doesntExist()) {
                $oldRoom->update(['status' => RoomStatus::Available]);
            }

            if ($validated['move_to_another_room'] ?? false) {
                $existingLease = $targetRoom->leases()->where('status', 'active')->first();

                $lease->load('tenants');

                if ($existingLease) {
                    $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

                    foreach ($lease->tenants as $tenant) {
                        if (! $existingTenantIds->contains($tenant->id)) {
                            $existingLease->tenants()->attach($tenant->id, [
                                'is_primary' => DB::raw('false'),
                            ]);
                        }
                    }
                } else {
                    $matchingRate = $targetRoom->rates()
                        ->where('billing_interval', $lease->billing_interval)
                        ->where('billing_unit', $lease->billing_unit)
                        ->first();

                    $newLease = $targetRoom->leases()->create([
                        'primary_tenant_id' => $lease->primary_tenant_id,
                        'start_date' => $validated['move_out_date'],
                        'rent_amount' => $lease->rent_amount,
                        'billing_interval' => $lease->billing_interval ?? 1,
                        'billing_unit' => $lease->billing_unit ?? 'month',
                        'is_custom_price' => $lease->is_custom_price ? DB::raw('true') : DB::raw('false'),
                        'room_rate_id' => $matchingRate?->id,
                        'deposit_amount' => $lease->deposit_amount,
                        'deposit_paid_at' => $lease->deposit_paid_at,
                        'deposit_refund_amount' => null,
                        'deposit_refunded_at' => null,
                        'rent_due_day' => $lease->rent_due_day,
                        'status' => 'active',
                        'notes' => 'Moved from room '.$lease->room->name.' on '.now()->format('Y-m-d'),
                    ]);

                    foreach ($lease->tenants as $tenant) {
                        $newLease->tenants()->attach($tenant->id, [
                            'is_primary' => $tenant->id === $lease->primary_tenant_id ? DB::raw('true') : DB::raw('false'),
                        ]);
                    }
                }

                $targetRoom->update(['status' => RoomStatus::Occupied]);
            }
        });

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

    public function move(MoveLeaseRequest $request, Property $property, Room $room, Lease $lease): RedirectResponse
    {
        $targetRoom = Room::findOrFail($request->validated('target_room_id'));

        $this->authorize('move', [$lease, $targetRoom]);

        DB::transaction(function () use ($lease, $targetRoom, $room) {
            $targetRoom = Room::lockForUpdate()->findOrFail($targetRoom->id);

            $lease->load('tenants');

            $activeTenantsCount = DB::table('lease_tenant')
                ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
                ->where('leases.room_id', $targetRoom->id)
                ->where('leases.status', 'active')
                ->count();

            $existingLease = $targetRoom->leases()->where('status', 'active')->first();

            $incomingTenantIds = $lease->tenants->pluck('id')->toArray();

            $incomingCount = $existingLease
                ? count(array_diff($incomingTenantIds, $existingLease->tenants()->pluck('tenants.id')->all()))
                : count($incomingTenantIds);

            abort_if(($activeTenantsCount + $incomingCount) > $targetRoom->capacity, 422, __('Room capacity exceeded. Target room can only hold :capacity occupants.', ['capacity' => $targetRoom->capacity]));
            $lease->update([
                'end_date' => now(),
                'status' => 'terminated',
                'termination_date' => now(),
                'termination_reason' => 'Moved to room '.$targetRoom->name,
                'notes' => ($lease->notes ? $lease->notes."\n" : '').'Moved to room '.$targetRoom->name.' on '.now()->format('Y-m-d'),
            ]);

            $room->unsetRelation('leases');

            if ($room->leases()->where('status', 'active')->doesntExist()) {
                $room->update(['status' => RoomStatus::Available]);
            }

            $existingLease = $targetRoom->leases()->where('status', 'active')->first();

            $lease->load('tenants');

            if ($existingLease) {
                $existingTenantIds = $existingLease->tenants()->pluck('tenants.id');

                foreach ($lease->tenants as $tenant) {
                    if (! $existingTenantIds->contains($tenant->id)) {
                        $existingLease->tenants()->attach($tenant->id, [
                            'is_primary' => DB::raw('false'),
                        ]);
                    }
                }
            } else {
                $matchingRate = $targetRoom->rates()
                    ->where('billing_interval', $lease->billing_interval)
                    ->where('billing_unit', $lease->billing_unit)
                    ->first();

                $newLease = $targetRoom->leases()->create([
                    'primary_tenant_id' => $lease->primary_tenant_id,
                    'start_date' => now(),
                    'rent_amount' => $lease->rent_amount,
                    'billing_interval' => $lease->billing_interval ?? 1,
                    'billing_unit' => $lease->billing_unit ?? 'month',
                    'is_custom_price' => $lease->is_custom_price ? DB::raw('true') : DB::raw('false'),
                    'room_rate_id' => $matchingRate?->id,
                    'deposit_amount' => $lease->deposit_amount,
                    'deposit_paid_at' => $lease->deposit_paid_at,
                    'deposit_refund_amount' => $lease->deposit_refund_amount,
                    'deposit_refunded_at' => $lease->deposit_refunded_at,
                    'rent_due_day' => $lease->rent_due_day,
                    'status' => 'active',
                    'notes' => 'Moved from room '.$room->name.' on '.now()->format('Y-m-d'),
                ]);

                foreach ($lease->tenants as $tenant) {
                    $newLease->tenants()->attach($tenant->id, [
                        'is_primary' => $tenant->id === $lease->primary_tenant_id ? DB::raw('true') : DB::raw('false'),
                    ]);
                }
            }

            $targetRoom->update(['status' => RoomStatus::Occupied]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant moved to new room.')]);

        return to_route('properties.rooms.index', $property);
    }
}
