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
            // Lock payment row so concurrent execute() calls serialize
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            $previousIds = $payment->allocations()->pluck('invoice_id');
            $affectedIds = $previousIds
                ->push($payment->invoice_id)
                ->unique()
                ->values();
            $priorStatuses = Invoice::whereIn('id', $affectedIds)
                ->pluck('status', 'id');

            $payment->allocations()->delete();
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $payment->amount,
            ]);

            // A payment settles the invoice it was recorded against. Keep
            // allocations aligned with invoice_id, then recompute any invoice
            // this payment used to affect.
            $invoices = Invoice::whereIn('id', $affectedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                $invoice->recalculateStatus();

                if ($invoice->status === InvoiceStatus::Paid
                    && ($priorStatuses[$invoice->id] ?? null) !== InvoiceStatus::Paid->value
                ) {
                    DB::afterCommit(fn () => InvoiceFullyPaid::dispatch($invoice));
                }
            }
        });
    }
}
