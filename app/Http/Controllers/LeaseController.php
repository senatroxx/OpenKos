<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\MoveOutLease;
use App\Actions\Leases\RenewLease;
use App\Actions\Reminders\ForceSendReminder;
use App\Business\Leases\LeaseStatusValidator;
use App\Data\Lease\CreateLeaseData;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Events\Lease\LeaseCreated;
use App\Events\Lease\LeaseStatusChanged;
use App\Events\Unit\UnitStatusChanged;
use App\Http\Requests\Lease\MoveLeaseRequest;
use App\Http\Requests\Lease\MoveOutRequest;
use App\Http\Requests\Lease\RenewLeaseRequest;
use App\Http\Requests\Lease\StoreLeaseRequest;
use App\Http\Requests\Lease\UpdateLeaseRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Unit;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function __construct(
        private LeaseStatusValidator $leaseStatusValidator,
    ) {}

    public function show(Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $lease->load([
            'tenants:id,name,phone',
            'primaryTenant:id,name,phone',
            'unit.property.city',
            'payments.confirmedBy:id,name',
            'payments.proofs',
            'payments.invoice:id,period_start,period_end,reference,status',
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
                Column::make('invoice.period_start', 'Period')
                    ->sortable(function (Builder $q, string $direction) {
                        $q->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
                            ->orderBy('invoices.period_start', $direction)
                            ->select('payments.*');
                    }),
                Column::make('payment_date', 'Paid on')->sortable(),
                Column::make('amount', 'Amount')->sortable(),
                Column::make('payment_method', 'Method')->sortable(),
                Column::make('invoice.reference', 'Reference')
                    ->sortable(function (Builder $q, string $direction) {
                        $q->orderBy(
                            Invoice::select('reference')
                                ->whereColumn('id', 'payments.invoice_id'),
                            $direction
                        );
                    })
                    ->searchable(function (Builder $q, string $search) {
                        $q->whereHas('invoice', fn (Builder $q) => $q->where(DB::raw('lower(reference)'), 'like', '%'.mb_strtolower($search).'%'));
                    }),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', array_map(fn (PaymentStatus $s) => $s->value, PaymentStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('payments.status', $value)),
                Filter::select('payment_method', 'Method', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('payment_method', $value)),
            ])
            ->defaultSort('-payment_date');

        $result = $table->paginate(
            $lease->payments()->with(['confirmedBy:id,name', 'proofs', 'invoice:id,reference,period_start,period_end,status']),
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
                ->whereHas('payment.invoice', fn ($q) => $q->where('lease_id', $lease->id))
                ->with('payment:id,invoice_id,amount,status', 'payment.invoice:id,period_start'),
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
            ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone', 'payments.confirmedBy:id,name', 'payments.proofs', 'payments.invoice:id,period_start,period_end,reference,status'])
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
                Filter::select('status', 'Status', [LeaseStatus::Active->value, LeaseStatus::Terminated->value])
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('payment_status', 'Payment', [
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                ])
                    ->query(function (Builder $q, string $value) {
                        $periodStart = Carbon::now()->startOfMonth()->startOfDay();
                        $periodEnd = Carbon::now()->endOfMonth()->endOfDay();

                        return $value === 'paid'
                            ? $q->whereHas('invoices', fn (Builder $q) => $q
                                ->where('status', InvoiceStatus::Paid->value)
                                ->whereBetween('period_start', [$periodStart, $periodEnd]))
                            : $q->where('status', LeaseStatus::Active->value)->whereDoesntHave('invoices', fn (Builder $q) => $q
                                ->where('status', InvoiceStatus::Paid->value)
                                ->whereBetween('period_start', [$periodStart, $periodEnd]));
                    }),
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
            ->addSelect(['payment_status' => Invoice::query()
                ->selectRaw("CASE WHEN COUNT(*) > 0 THEN 'paid' ELSE 'overdue' END")
                ->whereColumn('lease_id', 'leases.id')
                ->where('status', InvoiceStatus::Paid->value)
                ->whereBetween('period_start', [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()]),
            ])
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'unit.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ));

        $result = $table->paginate($query, $request, 'leases');

        $leases = $result['leases'];
        $leases->loadMissing(['unit.property.city', 'payments.confirmedBy:id,name', 'payments.proofs', 'payments.invoice:id,period_start,period_end,reference,status']);

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
            ->where('status', LeaseStatus::Active->value)
            ->when($accessibleQuery)
            ->count();

        $periodStart = Carbon::now()->startOfMonth()->startOfDay();
        $periodEnd = Carbon::now()->endOfMonth()->endOfDay();

        $collectedThisMonth = (float) Payment::query()
            ->whereNotIn('status', [PaymentStatus::Cancelled->value])
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereBetween('period_start', [$periodStart, $periodEnd])
                ->whereHas('lease', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)->when($accessibleQuery)))
            ->sum('amount');

        $overdueAmount = (float) Invoice::query()
            ->overdue()
            ->whereHas('lease', fn (Builder $q) => $q->where('status', LeaseStatus::Active->value)->when($accessibleQuery))
            ->sum(DB::raw('total - amount_paid'));

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
            billingStrategy: $request->billing_strategy,
            unitRateId: $request->unit_rate_id,
            depositAmount: $request->deposit_amount,
            depositPaidAt: $request->deposit_paid_at,
            depositRefundAmount: $request->deposit_refund_amount,
            depositRefundedAt: $request->deposit_refunded_at,
            rentDueDay: $request->rent_due_day,
            notes: $request->notes,
        );

        $oldUnitStatus = $unit->status;

        $lease = $action->execute($unit, $data);

        $lease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

        if ($lease->wasRecentlyCreated) {
            LeaseCreated::dispatch($lease, $lease->tenants->pluck('id')->toArray(), actorId: Auth::id());
        }

        $unit->refresh();
        if ($oldUnitStatus !== $unit->status) {
            UnitStatusChanged::dispatch($unit, $oldUnitStatus, $unit->status, actorId: Auth::id());
        }

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

        $oldStatus = $lease->status;

        $this->leaseStatusValidator->validate($oldStatus, LeaseStatus::Terminated);

        DB::transaction(function () use ($lease, $unit) {
            $lease->update([
                'end_date' => now(),
                'status' => LeaseStatus::Terminated,
                'termination_date' => now(),
                'termination_reason' => request('reason'),
            ]);

            $unit->unsetRelation('leases');

            if ($unit->leases()->where('status', LeaseStatus::Active->value)->doesntExist() && $unit->status !== UnitStatus::Maintenance) {
                $oldUnitStatus = $unit->status;
                $unit->update(['status' => UnitStatus::Available]);

                if ($oldUnitStatus !== $unit->status) {
                    UnitStatusChanged::dispatch($unit, $oldUnitStatus, $unit->status, actorId: Auth::id());
                }
            }
        });

        LeaseStatusChanged::dispatch($lease, $oldStatus, LeaseStatus::Terminated, actorId: Auth::id());

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

        $oldLeaseStatus = $lease->status;
        $sourceUnit = $lease->unit;
        $oldSourceStatus = $sourceUnit->status;

        $oldTargetStatus = $targetUnit?->status;

        $result = $action->execute($lease, $data);

        if ($result->failed()) {
            abort(422, $result->error);
        }

        $lease->refresh();
        if ($oldLeaseStatus !== $lease->status) {
            LeaseStatusChanged::dispatch($lease, $oldLeaseStatus, $lease->status, actorId: Auth::id());
        }

        $sourceUnit->refresh();
        if ($oldSourceStatus !== $sourceUnit->status) {
            UnitStatusChanged::dispatch($sourceUnit, $oldSourceStatus, $sourceUnit->status, actorId: Auth::id());
        }

        if ($targetUnit) {
            $targetUnit->refresh();
            if ($oldTargetStatus !== $targetUnit->status) {
                UnitStatusChanged::dispatch($targetUnit, $oldTargetStatus, $targetUnit->status, actorId: Auth::id());
            }
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

        $oldLeaseStatus = $lease->status;

        $result = $action->execute($lease, $request->toData());

        if ($result->failed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result->error]);

            return back();
        }

        $lease->refresh();
        if ($oldLeaseStatus !== $lease->status) {
            LeaseStatusChanged::dispatch($lease, $oldLeaseStatus, $lease->status, actorId: Auth::id());
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

        $oldLeaseStatus = $lease->status;
        $oldSourceStatus = $unit->status;
        $oldTargetStatus = $targetUnit->status;

        $result = $action->execute($lease, $data);

        if ($result->failed()) {
            abort(422, $result->error);
        }

        $lease->refresh();
        if ($oldLeaseStatus !== $lease->status) {
            LeaseStatusChanged::dispatch($lease, $oldLeaseStatus, $lease->status, actorId: Auth::id());
        }

        $unit->refresh();
        if ($oldSourceStatus !== $unit->status) {
            UnitStatusChanged::dispatch($unit, $oldSourceStatus, $unit->status, actorId: Auth::id());
        }

        $targetUnit->refresh();
        if ($oldTargetStatus !== $targetUnit->status) {
            UnitStatusChanged::dispatch($targetUnit, $oldTargetStatus, $targetUnit->status, actorId: Auth::id());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant moved to new unit.')]);

        return to_route('properties.units.index', $property);
    }
}
