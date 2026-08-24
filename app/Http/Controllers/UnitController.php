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
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(
        private InstallationCurrencySettings $currencies,
        private MoneyConverter $money,
    ) {}

    public function show(Property $property, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        $this->loadWorkspaceUnit($unit);

        return Inertia::render('properties/units/show', [
            'property' => $property,
            'unit' => $unit,
        ]);
    }

    public function rates(Property $property, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        $this->loadWorkspaceUnit($unit);

        return Inertia::render('properties/units/rates', [
            'property' => $property,
            'unit' => $unit,
        ]);
    }

    private function loadWorkspaceUnit(Unit $unit): void
    {
        $unit->load(['property.city', 'activeRates', 'rates'])
            ->loadCount(['leases as active_leases' => fn (Builder $q) => $q->where('status', 'active')])
            ->load(['leases' => fn ($q) => $q->where('status', 'active')
                ->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
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
                ->with([
                    'leases' => fn ($q) => $q->where('status', 'active')->with(['tenants:id,name,phone', 'primaryTenant:id,name,phone']),
                    'activeRates',
                    'rates',
                ]);

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
            ->with(['property.city', 'activeRates'])
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

        $validated = $request->validated();
        $rates = $validated['rates'] ?? [];
        unset($validated['rates']);

        DB::transaction(function () use ($property, $validated, $rates): void {
            $this->assertNewRateCurrenciesSupported($rates);

            $unit = $property->units()->create($validated);

            foreach ($rates as $rate) {
                $unit->rates()->create([
                    'billing_interval' => $rate['billing_interval'],
                    'billing_unit' => $rate['billing_unit'],
                    'amount' => $rate['amount'],
                    'currency' => $rate['currency'] ?? null,
                    'is_active' => $rate['is_active'] ?? true,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit created.')]);

        return back();
    }

    public function update(UpdateUnitRequest $request, Property $property, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $validated = $request->validated();
        $rates = $validated['rates'] ?? [];
        $expectedUpdatedAt = CarbonImmutable::parse($validated['updated_at']);
        unset($validated['updated_at']);
        unset($validated['rates']);

        try {
            DB::transaction(function () use ($unit, $validated, $rates, $request, $expectedUpdatedAt): void {
                $lockedUnit = Unit::query()->lockForUpdate()->findOrFail($unit->id);

                if (! $lockedUnit->updated_at?->equalTo($expectedUpdatedAt)) {
                    throw ValidationException::withMessages([
                        'updated_at' => __('This unit changed while you were editing it. Refresh and try again.'),
                    ]);
                }

                $this->assertNewRateCurrenciesSupported($rates);

                $lockedUnit->update($validated);

                if (! $request->has('rates')) {
                    return;
                }

                $keepIds = collect($rates)
                    ->pluck('id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                if ($keepIds === []) {
                    $lockedUnit->rates()->where('is_active', true)->update(['is_active' => false]);
                } else {
                    $lockedUnit->rates()
                        ->where('is_active', true)
                        ->whereNotIn('id', $keepIds)
                        ->update(['is_active' => false]);
                }

                foreach ($rates as $rate) {
                    if (isset($rate['id'])) {
                        $unitRate = $lockedUnit->rates()->whereKey($rate['id'])->firstOrFail();
                        $unitRate->update([
                            'amount' => $rate['amount'],
                            'is_active' => $rate['is_active'] ?? true,
                        ]);
                    } else {
                        $lockedUnit->rates()->create([
                            'billing_interval' => $rate['billing_interval'],
                            'billing_unit' => $rate['billing_unit'],
                            'amount' => $rate['amount'],
                            'currency' => $rate['currency'] ?? null,
                            'is_active' => $rate['is_active'] ?? true,
                        ]);
                    }
                }

                $lockedUnit->touch();
            });
        } catch (QueryException $exception) {
            if (! $this->isUnitRateUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'rates' => __('A rate with the same billing period and currency already exists.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unit updated.')]);

        return back();
    }

    private function isUnitRateUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unit_rates_unit_interval_unit_currency_unique')
            || (
                str_contains($message, 'unit_rates')
                && str_contains($message, 'billing_interval')
                && str_contains($message, 'billing_unit')
                && str_contains($message, 'currency')
                && (str_contains($message, 'unique') || str_contains($message, 'duplicate'))
            );
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

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    private function assertNewRateCurrenciesSupported(array $rates): void
    {
        $this->currencies->lockForUpdate();

        $defaultCurrency = $this->currencies->default(fresh: true);
        $supportedCurrencies = $this->currencies->freshSupported();

        foreach ($rates as $index => $rate) {
            if (isset($rate['id'])) {
                continue;
            }

            $currency = isset($rate['currency'])
                ? $this->money->normalizeCurrency($rate['currency'])
                : $defaultCurrency;

            if (! in_array($currency, $supportedCurrencies, true)) {
                throw ValidationException::withMessages([
                    "rates.{$index}.currency" => __('This currency is not enabled for new pricing rates.'),
                ]);
            }

            try {
                $this->money->normalizeAmount((string) $rate['amount'], $currency);
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    "rates.{$index}.amount" => __('The amount has too many decimal places for this currency.'),
                ]);
            }
        }
    }
}
