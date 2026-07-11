<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
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
            // Track invoices previously affected so we can reset their
            // amount_paid before re-computing from new allocations.
            $previousIds = $payment->allocations()->pluck('invoice_id');
            $priorStatuses = Invoice::whereIn('id', $previousIds)
                ->pluck('status', 'id');
            Invoice::whereIn('id', $previousIds)->update([
                'amount_paid' => 0,
                'status' => InvoiceStatus::Pending,
            ]);

            $payment->allocations()->delete();

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

            // Update invoice balances from allocations (not from payments
            // by invoice_id, which would miss cross-invoice allocations).
            foreach ($affected as $invoice) {
                $paid = (float) $invoice->allocations()
                    ->whereHas('payment', fn ($q) => $q->where('status', PaymentStatus::Confirmed->value))
                    ->sum('amount');

                $status = match (true) {
                    $paid >= (float) $invoice->total => InvoiceStatus::Paid,
                    $paid > 0 => InvoiceStatus::Partial,
                    default => InvoiceStatus::Pending,
                };

                $invoice->update([
                    'amount_paid' => $paid,
                    'status' => $status,
                ]);

                if ($status === InvoiceStatus::Paid
                    && ($priorStatuses[$invoice->id] ?? null) !== InvoiceStatus::Paid
                ) {
                    // ponytail: dispatched via afterCommit so listeners
                    // never see uncommitted data.
                    DB::afterCommit(fn () => InvoiceFullyPaid::dispatch($invoice));
                }
            }
        });
    }
}
