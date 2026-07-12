<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RentController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $now = now();

        $accessiblePropertyIds = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->pluck('id');

        $urgency = $request->query('urgency', '');

        // --- Tab counts ---

        $tabCounts = [
            'overdue' => $this->countPayable($accessiblePropertyIds, $now, '<'),
            'due_today' => $this->countPayable($accessiblePropertyIds, $now, '='),
            'upcoming' => $this->countPayable($accessiblePropertyIds, $now, '>'),
            'partial' => Invoice::query()
                ->where('status', InvoiceStatus::Partial->value)
                ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
                ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds))
                ->count(),
            'paid' => Invoice::query()
                ->where('status', InvoiceStatus::Paid->value)
                ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
                ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds))
                ->count(),
        ];

        // --- Outstanding card ---

        $outstandingCount = $tabCounts['overdue'] + $tabCounts['due_today'] + $tabCounts['upcoming'];

        $outstandingAmount = (int) Invoice::query()
            ->payable()
            ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds))
            ->sum(DB::raw('total - amount_paid'));

        // --- Progress ---

        $progressTotal = $outstandingCount + $tabCounts['paid'];

        $collectedAmount = (int) Payment::where('status', PaymentStatus::Confirmed->value)
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
                ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds)))
            ->sum('amount');

        $lastPayment = Payment::where('status', PaymentStatus::Confirmed->value)
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
                ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds)))
            ->latest('payment_date')
            ->first();

        // --- Queue table ---

        $isPaidTab = $urgency === 'paid';
        $isPartialTab = $urgency === 'partial';

        $queueQuery = Invoice::query()
            ->with(['lease.primaryTenant', 'lease.tenants', 'lease.unit.property'])
            ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds));

        if (! $isPaidTab && ! $isPartialTab) {
            $queueQuery->payable();
        } elseif ($isPartialTab) {
            $queueQuery->where('status', InvoiceStatus::Partial->value);
        } else {
            $queueQuery->where('status', InvoiceStatus::Paid->value);
        }

        $table = Table::make()
            ->columns([
                Column::make('tenant_name', 'Tenant')->searchable(function (Builder $q, string $search): void {
                    $q->whereHas('lease.tenants', function (Builder $q) use ($search): void {
                        $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
                    })->orWhereHas('lease', function (Builder $q) use ($search): void {
                        $q->whereHas('unit', function (Builder $q) use ($search): void {
                            $q->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
                        });
                    });
                }),
                Column::make('urgency', 'Status'),
                Column::make('total', 'Amount')->sortable(),
                Column::make('outstanding', 'Outstanding'),
                Column::make('due_date', 'Due')->sortable(),
            ])
            ->filters([
                Filter::select('urgency', 'Status', [
                    ['value' => 'overdue', 'label' => 'Overdue'],
                    ['value' => 'due_today', 'label' => 'Due Today'],
                    ['value' => 'upcoming', 'label' => 'Upcoming'],
                    ['value' => 'partial', 'label' => 'Partial'],
                    ['value' => 'paid', 'label' => 'Paid'],
                ])
                    ->query(function (Builder $q, string $value) use ($now, $isPaidTab, $isPartialTab): void {
                        if ($isPaidTab || $isPartialTab) {
                            return;
                        }

                        match ($value) {
                            'overdue' => $q->where('due_date', '<', $now->toDateString()),
                            'due_today' => $q->whereDate('due_date', '=', $now->toDateString()),
                            'upcoming' => $q->where('due_date', '>', $now->toDateString()),
                            default => null,
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
                        'lease.unit',
                        fn (Builder $q) => $q->whereIn('property_id', explode(',', $value)),
                    )),
            ])
            ->defaultSort('due_date');

        $needsAttention = $table->paginate($queueQuery, $request, 'entries');

        $entries = collect($needsAttention['entries']->items())
            ->map(fn (Invoice $invoice) => $this->transformInvoice($invoice, $now))
            ->values();

        $needsAttention['entries'] = $needsAttention['entries']->setCollection($entries);

        // --- Recent Payments ---

        $recentPayments = Payment::with([
            'invoice' => fn ($q) => $q->with(['lease.primaryTenant', 'lease.tenants', 'lease.unit.property']),
        ])
            ->where('status', PaymentStatus::Confirmed->value)
            ->whereHas('invoice.lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds))
            ->latest('payment_date')
            ->limit(10)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => (string) $p->amount,
                'payment_date' => $p->payment_date->toDateString(),
                'payment_method' => $p->payment_method,
                'tenant_name' => $p->invoice?->lease?->tenants?->pluck('name')->join(', ')
                    ?: $p->invoice?->lease?->primaryTenant?->name ?? '—',
                'invoice_id' => $p->invoice_id,
                'invoice_reference' => $p->invoice?->reference ?? '—',
                'lease_id' => $p->invoice?->lease_id ?? null,
            ]);

        // --- Recent Reminders ---

        $recentReminders = ReminderLog::with(['lease.primaryTenant', 'lease.tenants'])
            ->whereHas('lease', fn (Builder $q) => $q
                ->where('status', 'active')
                ->whereHas(
                    'unit',
                    fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds),
                ))
            ->latest('sent_at')
            ->limit(10)
            ->get()
            ->map(fn (ReminderLog $r) => [
                'id' => $r->id,
                'lease_id' => $r->lease_id,
                'tenant_name' => $r->lease?->tenants?->pluck('name')->join(', ')
                    ?: $r->lease?->primaryTenant?->name ?? '—',
                'reminder_type' => $r->reminder_type,
                'channel' => $r->channel,
                'scheduled_for' => $r->scheduled_for?->toDateString(),
                'sent_at' => $r->sent_at?->toDateTimeString() ?? null,
                'overdue_days' => $r->overdue_days,
            ]);

        return Inertia::render('dashboard/rent', [
            ...$needsAttention,
            'outstanding' => [
                'count' => $outstandingCount,
                'amount' => $outstandingAmount,
            ],
            'tab_counts' => $tabCounts,
            'progress' => [
                'processed' => $tabCounts['paid'],
                'total' => $progressTotal,
                'amount_collected' => $collectedAmount,
                'last_payment_at' => $lastPayment?->payment_date?->toDateTimeString() ?? null,
            ],
            'recent_payments' => $recentPayments,
            'recent_reminders' => $recentReminders,
        ]);
    }

    private function countPayable(Collection $propertyIds, CarbonInterface $now, string $operator): int
    {
        $query = Invoice::query()
            ->payable()
            ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $propertyIds));

        match ($operator) {
            '<' => $query->where('due_date', '<', $now->toDateString()),
            '=' => $query->whereDate('due_date', '=', $now->toDateString()),
            '>' => $query->where('due_date', '>', $now->toDateString()),
            default => null,
        };

        return $query->count();
    }

    private function transformInvoice(Invoice $invoice, CarbonInterface $now): array
    {
        $lease = $invoice->lease;
        $tenants = $lease->tenants;
        $unit = $lease->unit;
        $dueDate = $invoice->due_date;

        $daysOverdue = $dueDate->isToday()
            ? null
            : ($dueDate->isPast() ? (int) $dueDate->startOfDay()->diffInDays($now->startOfDay()) : null);

        $urgency = match (true) {
            $daysOverdue !== null => 'overdue',
            $dueDate->isToday() => 'due_today',
            $dueDate->isTomorrow() => 'due_tomorrow',
            $dueDate->isFuture() && $dueDate->diffInDays($now->startOfDay()) <= 7 => 'due_soon',
            default => 'upcoming',
        };

        return [
            'id' => $invoice->id,
            'lease_id' => $lease->id,
            'tenant_name' => $tenants->pluck('name')->join(', ') ?: ($lease->primaryTenant?->name ?? '—'),
            'unit_name' => $unit?->name ?? '—',
            'property_name' => $unit?->property?->name ?? '—',
            'reference' => $invoice->reference,
            'period_start' => $invoice->period_start->toDateString(),
            'period_end' => $invoice->period_end->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'total' => (string) $invoice->total,
            'amount_paid' => (string) $invoice->amount_paid,
            'outstanding' => $invoice->outstanding,
            'days_overdue' => $daysOverdue,
            'urgency' => $urgency,
            'status' => $invoice->status->value,
        ];
    }
}
