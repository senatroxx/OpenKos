<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceGenerated;
use App\Models\Lease;
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
            $existing = $lease->invoices()->get(['id', 'period_start'])->pluck('period_start');

            foreach ($lease->schedule() as $period) {
                if ($period->period_start->gt($horizon)) {
                    continue;
                }

                if ($existing->contains(fn ($start) => $start->isSameDay($period->period_start))) {
                    continue;
                }

                $invoice = DB::transaction(function () use ($lease, $period) {
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

                // Dispatched outside the transaction so plugin listeners
                // (payment gateways, tenant portal) never see a rollback.
                InvoiceGenerated::dispatch($invoice);

                $count++;
            }
        }

        return $count;
    }
}
