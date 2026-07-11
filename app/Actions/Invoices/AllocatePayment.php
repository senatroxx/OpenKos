<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceFullyPaid;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class AllocatePayment
{
    /**
     * Allocate a payment to outstanding invoices.
     *
     * Default strategy: oldest due-date first. The engine creates
     * allocation records, updates invoice amounts, and fires events.
     */
    public function execute(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $remaining = (float) $payment->amount;
            $affected = collect();

            $invoices = Invoice::where('lease_id', $payment->invoice->lease_id)
                ->payable()
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $outstanding = (float) $invoice->total - (float) $invoice->amount_paid;
                $allocated = min($remaining, $outstanding);

                if ($allocated <= 0) {
                    continue;
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocated,
                ]);

                $remaining -= $allocated;
                $affected->push($invoice);
            }

            // Recalculate status on every invoice that received an allocation
            foreach ($affected as $invoice) {
                $invoice->recalculateStatus();

                if ($invoice->status === InvoiceStatus::Paid) {
                    InvoiceFullyPaid::dispatch($invoice);
                }
            }
        });
    }
}
