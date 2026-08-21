<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Services\Payments\MoneyConverter;
use App\Support\DateTimeFormatter;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RentController extends Controller
{
    public function __invoke(Request $request, MoneyConverter $money): Response
    {
        $now = now();

        $accessiblePropertyIds = Property::query()
            ->when(! $request->user()->isOwner(), fn (Builder $q) => $q->whereHas(
                'users',
                fn (Builder $q) => $q->whereKey($request->user()->id),
            ))
            ->pluck('id');

        $urgency = $request->query('urgency', '');
        $today = $now->toDateString();
        $tomorrow = $now->copy()->addDay()->toDateString();
        $payableStatuses = [
            InvoiceStatus::Pending->value,
            InvoiceStatus::Partial->value,
        ];

        $invoiceScope = Invoice::query()
            ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds));

        $invoiceTable = DB::getQueryGrammar()->wrap((new Invoice)->getTable());
        $paymentTable = DB::getQueryGrammar()->wrap((new Payment)->getTable());
        $pendingPaymentsAlias = DB::getQueryGrammar()->wrap('pending_payments');

        // --- Tab counts ---

        $invoiceStats = (clone $invoiceScope)->selectRaw(
            <<<SQL
            COALESCE(SUM(CASE
                WHEN {$invoiceTable}.due_date < ? AND {$invoiceTable}.status IN (?, ?)
                THEN 1 ELSE 0
            END), 0) AS overdue,
            COALESCE(SUM(CASE
                WHEN {$invoiceTable}.due_date >= ?
                    AND {$invoiceTable}.due_date < ?
                    AND {$invoiceTable}.status IN (?, ?)
                THEN 1 ELSE 0
            END), 0) AS due_today,
            COALESCE(SUM(CASE
                WHEN {$invoiceTable}.due_date >= ? AND {$invoiceTable}.status IN (?, ?)
                THEN 1 ELSE 0
            END), 0) AS upcoming,
            COALESCE(SUM(CASE
                WHEN {$invoiceTable}.status = ?
                THEN 1 ELSE 0
            END), 0) AS partial,
            COALESCE(SUM(CASE
                WHEN {$invoiceTable}.status = ?
                THEN 1 ELSE 0
            END), 0) AS paid,
            COALESCE(SUM(CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM {$paymentTable} AS {$pendingPaymentsAlias}
                    WHERE {$pendingPaymentsAlias}.invoice_id = {$invoiceTable}.id
                        AND {$pendingPaymentsAlias}.status = ?
                )
                THEN 1 ELSE 0
            END), 0) AS pending_review
            SQL,
            [
                $today, ...$payableStatuses,
                $today, $tomorrow, ...$payableStatuses,
                $tomorrow, ...$payableStatuses,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Paid->value,
                PaymentStatus::Pending->value,
            ],
        )->first();

        $tabCounts = [
            'overdue' => (int) ($invoiceStats?->overdue ?? 0),
            'due_today' => (int) ($invoiceStats?->due_today ?? 0),
            'upcoming' => (int) ($invoiceStats?->upcoming ?? 0),
            'partial' => (int) ($invoiceStats?->partial ?? 0),
            'paid' => (int) ($invoiceStats?->paid ?? 0),
            'pending_review' => (int) ($invoiceStats?->pending_review ?? 0),
        ];

        // --- Outstanding card ---

        $outstandingCount = $tabCounts['overdue'] + $tabCounts['due_today'] + $tabCounts['upcoming'];
        $tabCounts['all'] = $outstandingCount;

        $outstandingInvoices = (clone $invoiceScope)
            ->whereIn('status', $payableStatuses)
            ->get(['currency', 'total', 'amount_paid']);
        $outstandingAmounts = $this->aggregateMoney(
            $outstandingInvoices,
            fn (Invoice $invoice): string => BigDecimal::of((string) $invoice->total)
                ->minus((string) $invoice->amount_paid)
                ->toString(),
        );

        // --- Progress ---

        $progressTotal = $outstandingCount + $tabCounts['paid'];

        $paymentStats = Payment::query()
            ->where('status', PaymentStatus::Confirmed->value)
            ->whereHas('invoice', fn (Builder $q) => $q
                ->whereHas('lease', fn (Builder $q) => $q->where('status', 'active'))
                ->whereHas('lease.unit', fn (Builder $q) => $q->whereIn('property_id', $accessiblePropertyIds)))
            ->selectRaw(
                'COALESCE(currency, ?) as currency, COALESCE(SUM(amount), 0) as amount, MAX(payment_date) as last_payment_at',
                [$money->normalizeCurrency()],
            )
            ->groupByRaw('COALESCE(currency, ?)', [$money->normalizeCurrency()])
            ->toBase()
            ->get();
        $collectedAmounts = $paymentStats
            ->map(fn (object $payment): array => [
                'currency' => (string) $payment->currency,
                'amount' => (string) $payment->amount,
            ])
            ->values()
            ->all();
        $currencies = collect([
            ...$outstandingAmounts,
            ...$collectedAmounts,
        ])->pluck('currency')->unique()->values();
        $outstandingAmounts = $this->completeMoneyGroups($outstandingAmounts, $currencies);
        $lastPaymentDate = $paymentStats->pluck('last_payment_at')->filter()->max();
        $lastPaymentAt = $lastPaymentDate ? Carbon::parse($lastPaymentDate)->toDateString() : null;

        // --- Queue table ---

        $isPaidTab = $urgency === 'paid';
        $isPartialTab = $urgency === 'partial';
        $isPendingReviewTab = $urgency === 'pending_review';

        $queueQuery = (clone $invoiceScope)
            ->with([
                'lease.primaryTenant',
                'lease.tenants',
                'lease.unit.property',
                'lineItems',
                'payments' => fn ($q) => $q
                    ->with(['confirmedBy:id,name', 'proofs'])
                    ->latest('payment_date')
                    ->latest('id'),
            ]);

        if ($isPendingReviewTab) {
            $queueQuery->whereHas('payments', fn (Builder $q) => $q->where('status', PaymentStatus::Pending->value));
        } elseif (! $isPaidTab && ! $isPartialTab) {
            $queueQuery->payable();
        } elseif ($isPartialTab) {
            $queueQuery->where('status', InvoiceStatus::Partial->value);
        } else {
            $queueQuery->where('status', InvoiceStatus::Paid->value);
        }

        $table = Table::make()
            ->columns([
                Column::make('lease_reference', 'Lease'),
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
                    ['value' => 'pending_review', 'label' => 'Pending Review'],
                    ['value' => 'partial', 'label' => 'Partial'],
                    ['value' => 'paid', 'label' => 'Paid'],
                ])
                    ->query(function (Builder $q, string $value) use ($now, $isPaidTab, $isPartialTab, $isPendingReviewTab): void {
                        if ($isPaidTab || $isPartialTab || $isPendingReviewTab) {
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
            ->defaultSort('due_date')
            ->withPerPage(25);

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
                'currency' => $p->currency,
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
                'sent_at' => DateTimeFormatter::nullableIso($r->sent_at),
                'overdue_days' => $r->overdue_days,
            ]);

        return Inertia::render('dashboard/rent', [
            ...$needsAttention,
            'outstanding' => [
                'count' => $outstandingCount,
                'amounts' => $outstandingAmounts,
            ],
            'tab_counts' => $tabCounts,
            'progress' => [
                'processed' => $tabCounts['paid'],
                'total' => $progressTotal,
                'amount_collected' => $collectedAmounts,
                'last_payment_at' => $lastPaymentAt,
            ],
            'recent_payments' => $recentPayments,
            'recent_reminders' => $recentReminders,
        ]);
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
            'lease_reference' => $lease->reference,
            'primary_tenant_id' => $lease->primary_tenant_id,
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
            'currency' => $invoice->currency,
            'days_overdue' => $daysOverdue,
            'urgency' => $urgency,
            'status' => $invoice->status->value,
            'pending_payment_review_count' => $invoice->payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Pending)
                ->count(),
            'line_items' => $invoice->lineItems->map(fn ($item) => [
                'id' => $item->id,
                'invoice_id' => $item->invoice_id,
                'type' => $item->type,
                'description' => $item->description,
                'amount' => (string) $item->amount,
            ])->values()->all(),
            'payments' => $invoice->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'payment_date' => $payment->payment_date->toDateString(),
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference_number,
                'notes' => $payment->notes,
                'status' => $payment->status->value,
                'confirmed_by' => $payment->confirmed_by,
                'confirmed_by_user' => $payment->confirmedBy ? [
                    'id' => $payment->confirmedBy->id,
                    'name' => $payment->confirmedBy->name,
                ] : null,
                'recorded_by' => $payment->recorded_by,
                'recorded_by_user' => null,
                'verified_by' => $payment->verified_by,
                'verified_by_user' => null,
                'verified_at' => DateTimeFormatter::nullableIso($payment->verified_at),
                'proofs' => $payment->proofs->map(fn (PaymentProof $proof) => [
                    'id' => $proof->id,
                    'payment_id' => $proof->payment_id,
                    'path' => $proof->path,
                    'original_name' => $proof->original_name,
                    'mime_type' => $proof->mime_type,
                    'created_at' => DateTimeFormatter::iso($proof->created_at),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Invoice|Payment>  $rows
     * @return array<int, array{currency: string, amount: string}>
     */
    private function aggregateMoney(Collection $rows, callable $amount): array
    {
        return $rows
            ->groupBy(fn (Invoice|Payment $row): string => $row->currency)
            ->map(function ($rows, string $currency) use ($amount): array {
                $total = $rows->reduce(
                    fn (BigDecimal $total, Invoice|Payment $row): BigDecimal => $total->plus($amount($row)),
                    BigDecimal::zero(),
                );

                return ['currency' => $currency, 'amount' => $total->toString()];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{currency: string, amount: string}>  $groups
     * @param  Collection<int, string>  $currencies
     * @return array<int, array{currency: string, amount: string}>
     */
    private function completeMoneyGroups(array $groups, Collection $currencies): array
    {
        $groupsByCurrency = collect($groups)->keyBy('currency');

        return $currencies->map(fn (string $currency): array => [
            'currency' => $currency,
            'amount' => $groupsByCurrency->get($currency)['amount'] ?? BigDecimal::zero()->toString(),
        ])->all();
    }
}
