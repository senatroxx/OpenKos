<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLease;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\DeleteTenant;
use App\Actions\Tenants\DisableTenantAccess;
use App\Actions\Tenants\InviteTenant;
use App\Data\Lease\CreateLeaseData;
use App\Enums\Permission;
use App\Enums\TenantDocumentType;
use App\Http\Requests\Tenant\AssignUnitRequest;
use App\Http\Requests\Tenant\InviteTenantRequest;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Pricing\EffectiveUnitRateResolver;
use App\Support\DelimitedValues;
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
            'user:id,email,email_verified_at,last_login_at,is_active,invited_at',
            'documents.media',
            'leases' => fn ($q) => $q->active()
                ->with(['property', 'unit', 'tenants:id,name,phone', 'primaryTenant:id,name,phone']),
        ])->loadCount(['leases as active_leases_count' => fn ($q) => $q->active()]);

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
            $tenant->leases()->with(['property', 'unit', 'tenants:id,name,phone', 'primaryTenant:id,name,phone']),
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

        $result = $table->paginate($tenant->documents()->with('media'), $request, 'documents');

        return Inertia::render('tenants/documents', [
            ...$result,
            'tenant' => $this->workspaceTenant($tenant),
        ]);
    }

    private function workspaceTenant(Tenant $tenant): Tenant
    {
        return $tenant->loadCount(['leases as active_leases_count' => fn ($q) => $q->active()]);
    }

    public function index(Request $request): Response
    {
        $statusValues = DelimitedValues::normalize($request->query('status'));
        $includeSensitiveSearch = $request->user()->isOwner()
            || $request->user()->can(Permission::TenantsExportSensitive->value);

        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(
                    fn (Builder $q, string $search) => $q->listSearch($search, $includeSensitiveSearch),
                ),
                Column::make('phone', 'Phone')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['active', 'inactive', 'archived'])
                    ->query(fn (Builder $q, string $value) => $q->statusFilter($value)),
                Filter::select('app_access', 'App Access', [
                    ['value' => 'active', 'label' => 'Has access'],
                    ['value' => 'invited', 'label' => 'Invite pending'],
                    ['value' => 'email_only', 'label' => 'Email only'],
                    ['value' => 'disabled', 'label' => 'Access disabled'],
                    ['value' => 'none', 'label' => 'No access'],
                ])
                    // Buckets mirror appAccessStatus() on the frontend.
                    ->query(fn (Builder $q, string $value) => $q->appAccessFilter($value)),
            ])
            ->defaultSort('name');

        $assignedPropertyIds = ! $request->user()->isOwner()
            ? $request->user()->properties()->pluck('properties.id')
            : null;

        $query = Tenant::query()
            ->when($statusValues !== [] && in_array('archived', $statusValues, true), fn (Builder $q) => $q->withTrashed())
            ->with(['user:id,email,email_verified_at,last_login_at,is_active,invited_at', 'documents.media', 'leases' => fn ($q) => $q->active()->with(['property', 'unit', 'tenants:id,name,phone', 'primaryTenant:id,name,phone'])])
            ->withCount(['leases as active_leases_count' => fn ($q) => $q->active()])
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereHas(
                'leases',
                fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds),
            ));

        $result = $table->paginate($query, $request, 'tenants');

        $availableUnits = Unit::query()
            ->with([
                'property.city',
                'activeRates',
                'unitType.activeRates',
                'leases' => fn ($q) => $q->active(),
            ])
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->whereHas('property', fn (Builder $query) => $query->supportsUnitInventory())
            ->when($assignedPropertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $assignedPropertyIds))
            ->orderBy('name')
            ->get();

        $availableUnits->each(fn (Unit $unit) => $unit->setAttribute(
            'effective_rates',
            app(EffectiveUnitRateResolver::class)->resolve($unit)->map(fn (array $item): array => [
                ...$item['rate']->toArray(),
                'source' => $item['source'],
            ])->values(),
        ));

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
            unitTypeRateId: $validated['unit_type_rate_id'] ?? null,
            depositAmount: $validated['deposit_amount'] ?? null,
            depositPaidAt: $validated['deposit_paid_at'] ?? null,
            depositRefundAmount: null,
            depositRefundedAt: null,
            rentDueDay: $validated['rent_due_day'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $action->execute($unit, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant assigned to unit.')]);

        return back();
    }

    public function store(StoreTenantRequest $request, InviteTenant $invite, CreateTenant $createTenant): RedirectResponse
    {
        $tenant = DB::transaction(function () use ($request, $invite, $createTenant) {
            $tenant = $createTenant->execute($request->safe()->except(['email', 'send_invite']));

            if ($email = $request->validated('email')) {
                $invite->execute($tenant, $email, $request->boolean('send_invite'));
            }

            return $tenant;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant created.')]);

        return back();
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant, InviteTenant $invite): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->update($request->safe()->except(['email', 'send_invite']));

        $email = $request->validated('email');

        if ($email) {
            $user = $tenant->user;
            $sendInvite = $request->boolean('send_invite');

            if (! $user) {
                $invite->execute($tenant, $email, $sendInvite);
            } elseif (! ($user->is_active && $user->email_verified_at)) {
                // Non-active account: email is editable here. Active accounts are
                // read-only in the form and their login email is ignored server-side.
                if ($user->email !== $email) {
                    $user->update(['email' => $email, 'invited_at' => null]);
                }

                if ($sendInvite) {
                    $invite->sendInvitation($user);
                }
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant updated.')]);

        return back();
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        $this->authorize('restore', $tenant);

        $tenant->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant restored.')]);

        return back();
    }

    public function invite(InviteTenantRequest $request, Tenant $tenant, InviteTenant $action): RedirectResponse
    {
        $this->authorize('invite', $tenant);

        if ($tenant->user_id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Tenant already has app access.')]);

            return back();
        }

        $sendInvite = $request->boolean('send_invite', true);

        $action->execute($tenant, $request->validated('email'), $sendInvite);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $sendInvite ? __('Tenant invited.') : __('Tenant email saved.'),
        ]);

        return back();
    }

    public function resendInvitation(Tenant $tenant, InviteTenant $action): RedirectResponse
    {
        $this->authorize('invite', $tenant);

        $user = $tenant->user;

        if (! $user) {
            return back()->withErrors(['invite' => __('Tenant has no user account. Invite them first.')]);
        }

        $action->sendInvitation($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return back();
    }

    public function disableAccess(Tenant $tenant, DisableTenantAccess $action): RedirectResponse
    {
        $this->authorize('invite', $tenant);

        $action->execute($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant access disabled.')]);

        return back();
    }

    public function destroy(Tenant $tenant, DeleteTenant $action): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $result = $action->execute($tenant);

        if ($result->failed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot archive a tenant with an active lease.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant archived.')]);

        return to_route('tenants.index');
    }
}
