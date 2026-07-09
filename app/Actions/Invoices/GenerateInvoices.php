<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceGenerated;
use App\Models\Invoice;
use App\Models\Lease;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GenerateInvoices
{
    /**
     * Materialize invoices for upcoming billing periods.
     *
     * Horizon is 2 months ahead — must stay >= the reminder lookahead so
     * reminders never encounter an un-invoiced period.
     */
    public function execute(?Lease $lease = null): int
    {
        $leases = $lease ? collect([$lease]) : Lease::active()->get();
        $horizon = now()->addMonthsNoOverflow(2)->endOfMonth();
        $count = 0;

        foreach ($leases as $lease) {
            foreach ($lease->schedule() as $period) {
                if ($period->period_start->gt($horizon)) {
                    continue;
                }

                try {
                    $invoice = DB::transaction(function () use ($lease, $period) {
                        $exists = Invoice::where('lease_id', $lease->id)
                            ->whereDate('period_start', $period->period_start)
                            ->lockForUpdate()
                            ->exists();

                        if ($exists) {
                            return null;
                        }

                        $invoice = $lease->invoices()->create([
                            'period_start' => $period->period_start,
                            'period_end' => $period->period_end,
                            'due_date' => $period->due_date,
                            'status' => InvoiceStatus::Pending,
                            'total' => $period->amount,
                        ]);

                        $invoice->lineItems()->create([
                            'type' => 'rent',
                            'description' => 'Rent '.$period->period_start->format('F Y'),
                            'amount' => $period->amount,
                        ]);

                        return $invoice;
                    });
                } catch (QueryException $e) {
                    // ponytail: swallow only the expected period-idempotency
                    // race (invoices_lease_id_period_start_unique). Any other
                    // 23505 (e.g. reference collision) is a genuine bug and
                    // must be rethrown so the operator sees the failure.
                    if ((string) $e->getCode() !== '23505'
                        || ! str_contains($e->getMessage(), 'invoices_lease_id_period_start_unique')
                    ) {
                        throw $e;
                    }

                    continue;
                }

                if ($invoice === null) {
                    continue;
                }

                // Dispatched after-commit so plugin listeners (payment
                // gateways, tenant portal) never see a rollback.
                DB::afterCommit(fn () => InvoiceGenerated::dispatch($invoice));

                $count++;
            }
        }

        return $count;
    }
}
