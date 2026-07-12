<?php

namespace App\Http\Controllers\Dashboard;

use App\Business\Dashboard\RentStatsCalculator;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RentController extends Controller
{
    public function __invoke(Request $request, RentStatsCalculator $stats): Response
    {
        $accessibleQuery = function (Builder $q) use ($request): void {
            if (! $request->user()->isOwner()) {
                $q->whereHas('unit.property.users', fn (Builder $q) => $q->whereKey($request->user()->id));
            }
        };

        $now = now();
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        $periodStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
        $periodEnd = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth()->endOfDay();

        $baseQuery = Lease::query()
            ->where('status', 'active')
            ->where('start_date', '<=', $now);

        ($accessibleQuery)($baseQuery);

        $allActive = (clone $baseQuery)->get();

        $statsResult = $stats->computeStats($allActive, $currentMonth, $currentYear);

        $table = Table::make()
            ->columns([
                Column::make('tenant_name', 'Tenant')->searchable(function (Builder $q, string $search): void {
                    $q->whereHas('tenants', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
                        ->orWhereHas('unit', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
                        ->orWhereHas('unit.property', fn (Builder $q) => $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']));
                }),
                Column::make('unit_name', 'Unit'),
                Column::make('property_name', 'Property'),
                Column::make('next_due_date', 'Due Date')->sortable(),
                Column::make('next_outstanding', 'Amount Due')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', [
                    ['value' => 'overdue', 'label' => 'Overdue'],
                    ['value' => 'due_today', 'label' => 'Due Today'],
                    ['value' => 'due_soon', 'label' => 'Due Soon'],
                    ['value' => 'paid', 'label' => 'Paid'],
                ])
                    ->query(function (Builder $q, string $value) use ($periodStart, $periodEnd): void {
                        $hasPayment = fn (Builder $q) => $q->whereHas('invoices', fn (Builder $q) => $q
                            ->where('status', InvoiceStatus::Paid->value)
                            ->whereBetween('period_start', [$periodStart, $periodEnd]));

                        $noPayment = fn (Builder $q) => $q->whereDoesntHave('invoices', fn (Builder $q) => $q
                            ->where('status', InvoiceStatus::Paid->value)
                            ->whereBetween('period_start', [$periodStart, $periodEnd]));

                        match ($value) {
                            'paid' => $hasPayment($q),
                            'overdue' => $noPayment($q)->whereHas('invoices', fn (Builder $q) => $q
                                ->payable()
                                ->whereDate('due_date', '<', now())),
                            'due_today' => $noPayment($q)->whereHas('invoices', fn (Builder $q) => $q
                                ->payable()
                                ->whereDate('due_date', '=', now())),
                            'due_soon' => $noPayment($q)->whereHas('invoices', fn (Builder $q) => $q
                                ->payable()
                                ->whereBetween('due_date', [now()->addDay(), now()->addDays(7)])),
                        };
                    }),
                Filter::select('properties', 'Property', function () use ($request): array {
                    return Property::query()
                        ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                            'users',
                            fn (Builder $q) => $q->whereKey($request->user()->id),
                        ))
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Property $p) => ['value' => (string) $p->id, 'label' => $p->name])
                        ->all();
                })
                    ->query(fn (Builder $q, string $value) => $q->whereHas(
                        'unit',
                        fn (Builder $q) => $q->whereIn('property_id', explode(',', $value)),
                    )),
            ])
            ->defaultSort('next_due_date');

        $query = (clone $baseQuery)
            ->with(['primaryTenant:id,name,phone', 'tenants:id,name,phone', 'unit:id,name,property_id', 'unit.property:id,name'])
            ->addSelect([
                'has_payment_this_month' => Invoice::query()
                    ->selectRaw('COUNT(*) > 0')
                    ->whereColumn('lease_id', 'leases.id')
                    ->where('status', InvoiceStatus::Paid->value)
                    ->whereBetween('period_start', [$periodStart, $periodEnd]),
                'next_due_date' => Invoice::query()
                    ->select('due_date')
                    ->whereColumn('lease_id', 'leases.id')
                    ->payable()
                    ->orderBy('due_date')
                    ->limit(1),
                'next_outstanding' => Invoice::query()
                    ->selectRaw('COALESCE(SUM(COALESCE(total, 0) - COALESCE(amount_paid, 0)), 0)')
                    ->whereColumn('lease_id', 'leases.id')
                    ->payable(),
            ]);

        $result = $table->paginate($query, $request, 'entries');

        $entries = collect($result['entries']->items())
            ->map(fn (Lease $lease) => $stats->transformEntry($lease))
            ->filter(fn (?array $entry) => $entry !== null)
            ->values();

        $result['entries'] = $result['entries']->setCollection($entries);

        return Inertia::render('dashboard/rent', [
            ...$result,
            'stats' => $statsResult,
        ]);
    }
}
