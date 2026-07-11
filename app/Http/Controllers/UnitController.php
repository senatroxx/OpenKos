<?php

namespace App\Http\Controllers;

use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function show(Property $property, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        $unit->load(['property.city', 'activeRates'])
            ->loadCount(['leases as active_leases' => fn (Builder $q) => $q->where('status', 'active')])
            ->load(['leases' => fn ($q) => $q->where('status', 'active')
                ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            ]);

        return Inertia::render('properties/units/show', [
            'property' => $property,
            'unit' => $unit,
        ]);
    }

    public function leaseHistory(Request $request, Property $property, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(leases.reference)'), 'like', $s)
                        ->orWhereHas('tenants', fn (Builder $q) => $q->where(DB::raw('lower(name)'), 'like', $s));
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
            $unit->leases()->withTrashed()->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
            $request,
            'leases',
        );

        return Inertia::render('properties/units/lease-history', [
            ...$result,
            'property' => $property->only('id', 'slug', 'name'),
            'unit' => $unit->only('id', 'slug', 'name', 'floor'),
        ]);
    }

    public function index(Request $request, Property $property): Response|JsonResponse
    {
        $this->authorize('viewAny', [Unit::class, $property]);

        $property = Property::withWorkspaceStats()->findOrFail($property->id);

        $archived = $request->query('status') === 'archived';

        $table = Table::make()
            ->columns([
                Column::make('name', 'Name')->sortable()->searchable(),
                Column::make('floor', 'Floor')->sortable()->searchable(),
                Column::make('size_sqm', 'Size')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('capacity', 'Capacity')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['available', 'occupied', 'maintenance', 'unavailable', 'archived'])
                    ->query(fn (Builder $q, string $value) => match ($value) {
                        'archived' => null,
                        default => $q->where('status', $value),
                    }),
            ])
            ->defaultSort('name');

        $query = $archived
            ? $property->units()->onlyTrashed()
            : $property->units()
                ->withCount([
                    'leases as active_leases' => fn (Builder $q) => $q->where('status', 'active'),
                ])
                ->with(['leases' => fn ($q) => $q->where('status', 'active')->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']), 'activeRates']);

        $result = $table->paginate($query, $request, 'units');

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        $tenantsList = Tenant::where('is_active', true)
            ->whereNull('deleted_at')
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'leases.unit.property.users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $availableUnits = $property->units()
            ->with('property.city')
            ->select(['id', 'slug', 'name', 'property_id', 'capacity'])
            ->withOccupiedCount()
            ->availableForAssignment()
            ->orderBy('name')
            ->get();

        return Inertia::render('properties/units/index', [
            ...$result,
            'property' => $property,
            'tenants' => $tenantsList,
            'availableUnits' => $availableUnits,
        ]);
    }

    public function store(StoreUnitRequest $request, Property $property): RedirectResponse
    {
        $this->authorize('create', [Unit::class, $property]);

        $unit = $property->units()->create($request->validated());

        if ($request->filled('rates')) {
            foreach ($request->rates as $rate) {
                $unit->rates()->create([
                    'billing_interval' => $rate['billing_interval'],
                    'billing_unit' => $rate['billing_unit'],
                    'amount' => $rate['amount'],
                    'is_active' => true,
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit created.')]);

        return to_route('properties.units.index', $property);
    }

    public function update(UpdateUnitRequest $request, Property $property, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        if ($request->has('rates')) {
            $keepIds = [];
            foreach ($request->rates as $rate) {
                if (isset($rate['id'])) {
                    $unit->rates()->where('id', $rate['id'])->update([
                        'billing_interval' => $rate['billing_interval'],
                        'billing_unit' => $rate['billing_unit'],
                        'amount' => $rate['amount'],
                        'is_active' => $rate['is_active'] ?? true,
                    ]);
                    $keepIds[] = $rate['id'];
                } else {
                    $new = $unit->rates()->create([
                        'billing_interval' => $rate['billing_interval'],
                        'billing_unit' => $rate['billing_unit'],
                        'amount' => $rate['amount'],
                        'is_active' => true,
                    ]);
                    $keepIds[] = $new->id;
                }
            }
            $unit->rates()->whereNotIn('id', $keepIds)->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit updated.')]);

        return to_route('properties.units.index', $property);
    }

    public function restore(Property $property, Unit $unit): RedirectResponse
    {
        $this->authorize('restore', $unit);

        $unit->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit restored.')]);

        return back();
    }

    public function destroy(Property $property, Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $deleted = DB::transaction(function () use ($unit) {
            $locked = Unit::lockForUpdate()->findOrFail($unit->id);

            if (Lease::where('unit_id', $locked->id)->where('status', LeaseStatus::Active)->exists()) {
                return false;
            }

            $locked->delete();

            return true;
        });

        if (! $deleted) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a unit with active leases.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit deleted.')]);

        return to_route('properties.units.index', $property);
    }

    public function maintenanceHistory(Request $request, Property $property, Unit $unit): Response
    {
        $this->authorize('viewAny', MaintenanceTicket::class);

        $table = Table::make()
            ->columns([
                Column::make('title', 'Title')->sortable()->searchable(function (Builder $q, string $search): void {
                    $s = '%'.mb_strtolower($search).'%';
                    $q->where(DB::raw('lower(title)'), 'like', $s)
                        ->orWhere(DB::raw('lower(reference)'), 'like', $s);
                }),
                Column::make('priority', 'Priority')->sortable(),
                Column::make('status', 'Status')->sortable(),
                Column::make('cost', 'Cost')->sortable(),
                Column::make('created_at', 'Created')->sortable(),
                Column::make('resolved_at', 'Resolved')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', array_map(fn (MaintenanceStatus $s) => $s->value, MaintenanceStatus::cases()))
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
                Filter::select('priority', 'Priority', ['low', 'medium', 'high', 'urgent'])
                    ->query(fn (Builder $q, string $value) => $q->where('priority', $value)),
            ])
            ->defaultSort('-created_at');

        $result = $table->paginate(
            $unit->maintenanceTickets()->with(['property:id,name', 'assignee:id,name', 'creator:id,name']),
            $request,
            'tickets',
        );

        return Inertia::render('properties/units/maintenance-history', [
            ...$result,
            'property' => $property->only('id', 'slug', 'name'),
            'unit' => $unit->only('id', 'slug', 'name', 'floor'),
        ]);
    }
}
