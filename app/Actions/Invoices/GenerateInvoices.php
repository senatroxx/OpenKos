<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceGenerated;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Services\Payments\MoneyConverter;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateInvoices
{
    private const MAX_UNIQUE_RETRIES = 3;

    public function __construct(private MoneyConverter $money) {}

    /**
     * Materialize invoices for upcoming billing periods.
     *
     * Horizon is 2 months ahead — must stay >= the reminder lookahead so
     * reminders never encounter an un-invoiced period.
     */
    public function execute(?Lease $lease = null): int
    {
        // ponytail: retry only known invoice-key races; sustained contention or
        // unrelated constraint errors must remain visible to the caller.
        for ($attempt = 1; $attempt <= self::MAX_UNIQUE_RETRIES; $attempt++) {
            try {
                return DB::transaction(fn (): int => $this->generateBatch($lease));
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_UNIQUE_RETRIES || ! $this->isRetryableInvoiceUniqueViolation($exception)) {
                    throw $exception;
                }
            }
        }

        return 0;
    }

    private function generateBatch(?Lease $lease): int
    {
        // ponytail: lock the selected lease set once; per-lease locking can
        // replace this if concurrent generation becomes a measured bottleneck.
        $leases = $lease
            ? Lease::query()->whereKey($lease->getKey())->lockForUpdate()->get()
            : Lease::active()->lockForUpdate()->get();

        if ($leases->isEmpty()) {
            return 0;
        }

        $horizon = now()->addMonthsNoOverflow(2)->endOfMonth();
        $candidates = [];

        foreach ($leases as $lockedLease) {
            foreach ($lockedLease->schedule() as $period) {
                if ($period->period_start->gt($horizon)) {
                    continue;
                }

                $currency = $lockedLease->currency;

                $candidates[] = [
                    'lease_id' => $lockedLease->getKey(),
                    'period_start' => $period->period_start,
                    'period_end' => $period->period_end,
                    'due_date' => $period->due_date,
                    'amount' => $this->money->normalizeAmount((string) $period->amount, $currency),
                    'currency' => $currency,
                ];
            }
        }

        if ($candidates === []) {
            return 0;
        }

        $leaseIds = $leases->modelKeys();
        $minimumPeriod = collect($candidates)->min('period_start')->toDateString();
        $existingKeys = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->whereBetween('period_start', [$minimumPeriod, $horizon])
            ->get(['lease_id', 'period_start'])
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $this->periodKey($invoice->lease_id, $invoice->period_start) => true,
            ]);

        $newCandidates = collect($candidates)
            ->reject(fn (array $candidate): bool => $existingKeys->has(
                $this->periodKey($candidate['lease_id'], $candidate['period_start'])
            ))
            ->values();

        if ($newCandidates->isEmpty()) {
            return 0;
        }

        $references = Invoice::nextReferences($newCandidates->count());
        $timestamp = now();
        $invoiceRows = $newCandidates->map(fn (array $candidate, int $index): array => [
            'lease_id' => $candidate['lease_id'],
            'reference' => $references[$index],
            'period_start' => $candidate['period_start']->toDateString(),
            'period_end' => $candidate['period_end']->toDateString(),
            'due_date' => $candidate['due_date']->toDateString(),
            'status' => InvoiceStatus::Pending->value,
            'total' => $candidate['amount'],
            'amount_paid' => 0,
            'currency' => $candidate['currency'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        Invoice::query()->insert($invoiceRows);

        $candidateKeys = $newCandidates->mapWithKeys(fn (array $candidate): array => [
            $this->periodKey($candidate['lease_id'], $candidate['period_start']) => $candidate,
        ]);
        $persistedInvoices = Invoice::query()
            ->whereIn('lease_id', $newCandidates->pluck('lease_id')->unique()->values())
            ->whereBetween('period_start', [$minimumPeriod, $horizon])
            ->get()
            ->keyBy(fn (Invoice $invoice): string => $this->periodKey($invoice->lease_id, $invoice->period_start));

        $createdInvoices = $newCandidates
            ->map(fn (array $candidate): ?Invoice => $persistedInvoices->get(
                $this->periodKey($candidate['lease_id'], $candidate['period_start'])
            ))
            ->filter()
            ->values();

        if ($createdInvoices->count() !== $newCandidates->count()) {
            throw new \LogicException('Invoice batch did not persist all generated periods.');
        }

        $lineItemRows = $createdInvoices->map(function (Invoice $invoice) use ($candidateKeys, $timestamp): array {
            $candidate = $candidateKeys->get($this->periodKey($invoice->lease_id, $invoice->period_start));

            return [
                'invoice_id' => $invoice->getKey(),
                'type' => 'rent',
                'description' => 'Rent '.$invoice->period_start->format('F Y'),
                'amount' => $candidate['amount'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        })->all();

        InvoiceLineItem::query()->insert($lineItemRows);

        foreach ($createdInvoices as $invoice) {
            $invoice->recordAudit('create');
        }

        DB::afterCommit(function () use ($createdInvoices): void {
            foreach ($createdInvoices as $invoice) {
                InvoiceGenerated::dispatch($invoice);
            }
        });

        return $createdInvoices->count();
    }

    private function periodKey(int $leaseId, CarbonInterface $periodStart): string
    {
        return $leaseId.'|'.$periodStart->toDateString();
    }

    private function isRetryableInvoiceUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $isUniqueViolation = match (DB::getDriverName()) {
            'pgsql' => (string) ($errorInfo[0] ?? $exception->getCode()) === '23505',
            'mysql' => (int) ($errorInfo[1] ?? 0) === 1062,
            'sqlite' => (int) ($errorInfo[1] ?? 0) === 19,
            default => false,
        };

        if (! $isUniqueViolation) {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'invoices_lease_id_period_start_unique')
            || str_contains($message, 'invoices.lease_id, invoices.period_start')
            || str_contains($message, 'invoices_reference_unique')
            || str_contains($message, 'invoices.reference');
    }
}
