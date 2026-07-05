<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\MoveOutLease;
use App\Actions\Leases\RenewLease;
use App\Actions\Reminders\ForceSendReminder;
use App\Data\Lease\CreateLeaseData;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Http\Requests\Lease\MoveLeaseRequest;
use App\Http\Requests\Lease\MoveOutRequest;
use App\Http\Requests\Lease\RenewLeaseRequest;
use App\Http\Requests\Lease\StoreLeaseRequest;
use App\Http\Requests\Lease\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Unit;
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
    public function show(Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $lease->load([
            'tenants:id,name,phone',
            'primaryTenant:id,name,phone',
            'unit.property.city',
            'payments.confirmedBy:id,name',
            'payments.proofs',
            'unitHistories.transferredBy:id,name',
        ]);

        return Inertia::render('leases/show', [
            'lease' => $lease,
        ]);
    }

    public function payments(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $table = Table::make()
            ->columns([
                Column::make('period_start', 'Period')->sortable(),
                Column::make('payment_date', 'Paid on')->sortable(),
                Column::make('amount', 'Amount')->sortable(),
                Column::make('payment_method', 'Method')->sortable(),
                Column::make('reference', 'Reference')->sortable()->searchable(),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', array_map(fn (PaymentStatus $s) => $s->value, PaymentStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('payment_method', 'Method', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('payment_method', $value)),
            ])
            ->defaultSort('-period_start');

        $result = $table->paginate(
            $lease->payments()->with(['confirmedBy:id,name', 'proofs']),
            $request,
            'payments',
        );

        return Inertia::render('leases/payments', [
            ...$result,
            'lease' => $lease->only('id', 'reference', 'status'),
        ]);
    }

    public function documents(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $table = Table::make()
            ->columns([
                Column::make('original_name', 'Name')->sortable()->searchable(),
                Column::make('mime_type', 'Type')->sortable(),
                Column::make('created_at', 'Uploaded')->sortable(),
            ])
            ->filters([
                Filter::select('payment_status', 'Payment status', array_map(fn (PaymentStatus $s) => $s->value, PaymentStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->whereHas('payment', fn (Builder $q) => $q->where('status', $value))),
            ])
            ->defaultSort('-created_at');

        $result = $table->paginate(
            PaymentProof::query()
                ->whereHas('payment', fn (Builder $q) => $q->where('paymentable_type', $lease->getMorphClass())->where('paymentable_id', $lease->id))
                ->with('payment:id,period_start,amount,status'),
            $request,
            'documents',
        );

        return Inertia::render('leases/documents', [
            ...$result,
            'lease' => $lease->only('id', 'reference', 'status'),
        ]);
    }

    public function index(Property $property, Unit $unit): Response
    {
        $this->authorize('viewAny', [Lease::class, $property]);

        $unit->load('property.city');

        $leases = $unit->leases()
            ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone', 'payments.confirmedBy:id,name', 'payments.proofs'])
            ->withTrashed()
            ->orderBy('created_at', 'desc')
            ->get()
            ->each->setRelation('unit', $unit);

        $availableUnits = $property->units()
            ->with('property.city')
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/units/leases/index', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug, 'city' => $property->city?->name],
            'unit' => $unit->only('id', 'slug', 'name', 'floor'),
            'leases' => $leases,
            'availableUnits' => $availableUnits,
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
                        ->orWhereHas('unit', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'))
                        ->orWhereHas('unit.property', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', '%'.mb_strtolower($search).'%'));
                }),
                Column::make('unit_name', 'Unit'),
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
                        'unit',
                        fn (Builder $q) => $q->whereIn('property_id', explode(',', $value)),
                    )),
            ])
            ->defaultSort('status,-start_date');

        $query = Lease::query()
            ->with(['primaryTenant:id,name,phone', 'tenants:id,name,phone', 'unit:id,slug,name,property_id', 'unit.property:id,slug,name'])
            ->addSelect(['payment_status' => Payment::query()
                ->selectRaw("CASE WHEN COUNT(*) > 0 THEN 'paid' ELSE 'overdue' END")
                ->whereColumn('paymentable_id', 'leases.id')
                ->where('paymentable_type', Lease::class)
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('period_start', now()->month)
                ->whereYear('period_start', now()->year),
            ])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'unit.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ));

        $result = $table->paginate($query, $request, 'leases');

        $leases = $result['leases'];
        $leases->loadMissing(['unit.property.city', 'payments.confirmedBy:id,name', 'payments.proofs']);

        $availableUnits = Unit::query()
            ->with('property.city')
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->availableForAssignment()
            ->orderBy('name')
            ->get();

        $accessibleQuery = fn (Builder $q) => $request->user()->isOwner()
            ? $q
            : $q->whereHas('unit.property.users', fn (Builder $q) => $q->whereKey($request->user()->id));

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
            'availableUnits' => $availableUnits,
            'stats' => [
                'active_leases' => $activeLeases,
                'collected_this_month' => $collectedThisMonth,
                'overdue_amount' => $overdueAmount,
            ],
        ]);
    }

    public function store(StoreLeaseRequest $request, Property $property, Unit $unit, CreateLease $action): RedirectResponse
    {
        $this->authorize('create', [Lease::class, $property]);

        $data = new CreateLeaseData(
            tenantIds: $request->tenant_ids,
            startDate: $request->start_date,
            endDate: $request->end_date,
            rentAmount: $request->rent_amount,
            billingInterval: $request->billing_interval,
            billingUnit: $request->billing_unit,
            unitRateId: $request->unit_rate_id,
            depositAmount: $request->deposit_amount,
            depositPaidAt: $request->deposit_paid_at,
            depositRefundAmount: $request->deposit_refund_amount,
            depositRefundedAt: $request->deposit_refunded_at,
            rentDueDay: $request->rent_due_day,
            notes: $request->notes,
        );

        $lease = $action->execute($unit, $data);

        $lease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

        $count = $lease->tenants->count();
        $message = $count > 1
            ? __(':count tenants assigned to unit.', ['count' => $count])
            : __('Tenant assigned to unit.');

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('properties.units.index', $property);
    }

    public function update(UpdateLeaseRequest $request, Property $property, Unit $unit, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);

        $lease->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease updated.')]);

        return to_route('properties.units.leases.index', [$property, $unit]);
    }

    public function destroy(Property $property, Unit $unit, Lease $lease): RedirectResponse
    {
        $this->authorize('delete', $lease);

        DB::transaction(function () use ($lease, $unit) {
            $lease->update([
                'end_date' => now(),
                'status' => 'terminated',
                'termination_date' => now(),
                'termination_reason' => request('reason'),
            ]);

            $unit->unsetRelation('leases');

            if ($unit->leases()->where('status', 'active')->doesntExist() && $unit->status !== UnitStatus::Maintenance) {
                $unit->update(['status' => UnitStatus::Available]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease terminated.')]);

        return to_route('properties.units.index', $property);
    }

    public function moveOut(MoveOutRequest $request, Lease $lease, MoveOutLease $action): RedirectResponse
    {
        $validated = $request->validated();

        $targetUnit = ($validated['move_to_another_unit'] ?? false)
            ? Unit::findOrFail($validated['target_unit_id'])
            : null;

        $this->authorize('moveOut', [$lease, $targetUnit]);

        $data = new MoveOutLeaseData(
            terminationDate: now()->toDateString(),
            endDate: $validated['move_out_date'],
            reason: $validated['reason'] ?? 'Moved out',
            depositReturned: $validated['deposit_returned'] ?? false,
            depositRefundAmount: $validated['deposit_refund_amount'] ?? null,
            notes: $validated['notes'] ?? null,
            moveToAnotherUnit: $validated['move_to_another_unit'] ?? false,
            targetUnitId: $validated['target_unit_id'] ?? null,
        );

        $result = $action->execute($lease, $data);

        if ($result->failed()) {
            abort(422, $result->error);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['move_to_another_unit']
                ? __('Tenant moved to new unit.')
                : __('Tenant moved out.'),
        ]);

        if ($validated['move_to_another_unit']) {
            return to_route('properties.units.index', $lease->unit->property_id);
        }

        return back();
    }

    public function renew(RenewLeaseRequest $request, Lease $lease, RenewLease $action): RedirectResponse
    {
        $this->authorize('renew', $lease);

        $result = $action->execute($lease, $request->toData());

        if ($result->failed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result->error]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lease renewed. New lease created.')]);

        return to_route('leases.index');
    }

    public function sendReminder(Lease $lease, ForceSendReminder $action): RedirectResponse
    {
        $this->authorize('sendReminder', $lease);

        $result = $action->execute($lease);

        $messages = [
            'no_contact' => __('Tenant has no phone number or email address.'),
            'all_paid' => __('All rent periods are paid.'),
            'already_sent' => __('Reminder already sent for this period.'),
            'sent' => __('Reminder sent.'),
        ];

        if ($result === 'sent') {
            Inertia::flash('toast', ['type' => 'success', 'message' => $messages['sent']]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => $messages[$result]]);
        }

        return back();
    }

    public function move(MoveLeaseRequest $request, Property $property, Unit $unit, Lease $lease, MoveOutLease $action): RedirectResponse
    {
        $targetUnit = Unit::findOrFail($request->validated('target_unit_id'));

        $this->authorize('move', [$lease, $targetUnit]);

        $data = new MoveOutLeaseData(
            terminationDate: now()->toDateString(),
            endDate: now()->toDateString(),
            reason: 'Moved to unit '.$targetUnit->name,
            notes: ($lease->notes ? $lease->notes."\n" : '').'Moved to unit '.$targetUnit->name.' on '.now()->format('Y-m-d'),
            moveToAnotherUnit: true,
            targetUnitId: $targetUnit->id,
            carryDepositRefund: true,
        );

        $result = $action->execute($lease, $data);

        if ($result->failed()) {
            abort(422, $result->error);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant moved to new unit.')]);

        return to_route('properties.units.index', $property);
    }
}
