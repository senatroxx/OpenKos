<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Data\Lease\CreateLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\TenantDocumentType;
use App\Http\Requests\Tenant\AssignUnitRequest;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
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

class TenantController extends Controller
{
    public function show(Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $tenant->load([
            'documents',
            'leases' => fn ($q) => $q->where('status', 'active')
                ->with(['unit.property', 'tenants:id,name,phone', 'primaryTenant:id,name,phone']),
        ])->loadCount(['leases as active_leases_count' => fn ($q) => $q->where('status', 'active')]);

        return Inertia::render('tenants/show', [
            'tenant' => $tenant,
        ]);
    }

    public function leases(Request $request, Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(leases.reference)'), 'like', $s)
                        ->orWhereHas('unit', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', $s));
                }),
                Column::make('start_date', 'Start')->sortable(),
                Column::make('end_date', 'End')->sortable(),
                Column::make('rent_amount', 'Rent')->sortable(),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'terminated'])
                    ->query(fn (Builder $q, string $value) => $q->where('leases.status', $value)),
            ])
            ->defaultSort('-start_date');

        $result = $table->paginate(
            $tenant->leases()->with(['unit.property', 'tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            $request,
            'leases',
        );

        return Inertia::render('tenants/leases', [
            ...$result,
            'tenant' => $this->workspaceTenant($tenant),
        ]);
    }

    public function documents(Request $request, Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $table = Table::make()
            ->columns([
                Column::make('original_name', 'Name')->sortable()->searchable(),
                Column::make('type', 'Type')->sortable(),
                Column::make('created_at', 'Uploaded')->sortable(),
            ])
            ->filters([
                Filter::select('type', 'Type', array_map(fn (TenantDocumentType $t) => $t->value, TenantDocumentType::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('type', $value)),
            ])
            ->defaultSort('-created_at');

        $result = $table->paginate($tenant->documents(), $request, 'documents');

        return Inertia::render('tenants/documents', [
            ...$result,
            'tenant' => $this->workspaceTenant($tenant),
        ]);
    }

    private function workspaceTenant(Tenant $tenant): Tenant
    {
        return $tenant->loadCount(['leases as active_leases_count' => fn ($q) => $q->where('status', 'active')]);
    }

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
                        'active' => $q->where('is_active', true),
                        'inactive' => $q->where('is_active', false),
                        'archived' => $q->onlyTrashed(),
                        default => $q,
                    }),
            ])
            ->defaultSort('name');

        $assignedPropertyIds = ! $request->user()->isOwner()
            ? $request->user()->properties()->pluck('properties.id')
            : null;

        $query = Tenant::query()
            ->with(['documents', 'leases' => fn ($q) => $q->where('status', 'active')->with(['unit.property', 'tenants:id,name,phone', 'primaryTenant:id,name,phone'])])
            ->withCount(['leases as active_leases_count' => fn ($q) => $q->where('status', 'active')])
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereHas(
                'leases',
                fn (Builder $q) => $q->whereHas('unit', fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds)),
            ));

        $result = $table->paginate($query, $request, 'tenants');

        $availableUnits = Unit::query()
            ->with(['property.city', 'activeRates'])
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds))
            ->orderBy('name')
            ->get();

        return Inertia::render('tenants/index', [
            ...$result,
            'availableUnits' => $availableUnits,
        ]);
    }

    public function assignUnit(AssignUnitRequest $request, Tenant $tenant, CreateLease $action): RedirectResponse
    {
        $validated = $request->validated();

        $unit = Unit::findOrFail($validated['unit_id']);

        $this->authorize('assignUnit', [Tenant::class, $unit]);

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
            billingStrategy: $validated['billing_strategy'] ?? null,
            unitRateId: $validated['unit_rate_id'] ?? null,
            depositAmount: $validated['deposit_amount'] ?? null,
            depositPaidAt: $validated['deposit_paid_at'] ?? null,
            depositRefundAmount: null,
            depositRefundedAt: null,
            rentDueDay: $validated['rent_due_day'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $action->execute($unit, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant assigned to unit.')]);

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

        $deleted = DB::transaction(function () use ($tenant) {
            // ponytail: locking the tenant row serializes with other tenant-row
            // locks but not with CreateLease::execute, which locks the unit.
            // A concurrent lease assignment between the exists() check and
            // delete() could leave an archived tenant on an active lease.
            // Fixing this would require CreateLease to also lock tenant rows.
            $locked = Tenant::lockForUpdate()->findOrFail($tenant->id);

            if ($locked->leases()->where('status', LeaseStatus::Active)->exists()) {
                return false;
            }

            $locked->delete();

            return true;
        });

        if (! $deleted) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot archive a tenant with an active lease.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant archived.')]);

        return to_route('tenants.index');
    }
}
