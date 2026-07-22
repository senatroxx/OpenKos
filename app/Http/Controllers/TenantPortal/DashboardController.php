<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\ReminderLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends TenantPortalController
{
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $lease = $tenant->leases()
            ->active()
            ->with('unit.property')
            ->latest('start_date')
            ->first();

        $invoices = $lease
            ? $lease->invoices()
                ->payable()
                ->orderBy('due_date')
                ->limit(5)
                ->get()
            : collect();

        $outstandingBalance = $lease
            ? (string) $lease->invoices()
                ->payable()
                ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as outstanding_balance')
                ->value('outstanding_balance')
            : '0';

        $payments = $lease
            ? $lease->payments()
                ->with('invoice:id,reference,period_start')
                ->latest('payment_date')
                ->limit(3)
                ->get()
            : collect();

        $reminders = $lease
            ? ReminderLog::query()
                ->where('lease_id', $lease->id)
                ->latest('sent_at')
                ->limit(5)
                ->get()
            : collect();

        return Inertia::render('tenant-portal/dashboard', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'lease' => $lease ? $this->dashboardLeasePayload($lease) : null,
            'rent' => [
                'status' => $this->rentStatus($lease, $invoices->first()),
                'outstanding_balance' => $outstandingBalance,
                'upcoming_invoices' => $invoices->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'lease_id' => $invoice->lease_id,
                    'reference' => $invoice->reference,
                    'period_start' => $invoice->period_start->toDateString(),
                    'period_end' => $invoice->period_end->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'status' => $invoice->status->value,
                    'display_status' => $invoice->display_status,
                    'total' => (string) $invoice->total,
                    'amount_paid' => (string) $invoice->amount_paid,
                    'outstanding' => $invoice->outstanding,
                ])->values(),
                'recent_payments' => $payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_reference' => $payment->invoice?->reference,
                    'period_start' => $payment->invoice?->period_start?->toDateString(),
                    'amount' => (string) $payment->amount,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status->value,
                ])->values(),
            ],
            'notifications' => $reminders->map(fn (ReminderLog $reminder) => [
                'id' => $reminder->id,
                'type' => $reminder->reminder_type,
                'channel' => $reminder->channel,
                'scheduled_for' => $reminder->scheduled_for?->toDateString(),
                'sent_at' => $reminder->sent_at?->toDateTimeString(),
                'overdue_days' => $reminder->overdue_days,
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardLeasePayload(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'reference' => $lease->reference,
            'start_date' => $lease->start_date->toDateString(),
            'end_date' => $lease->end_date?->toDateString(),
            'rent_amount' => (string) $lease->rent_amount,
            'billing_label' => $lease->billing_label,
            'status' => $lease->status->value,
            'unit' => $lease->unit ? [
                'id' => $lease->unit->id,
                'name' => $lease->unit->name,
                'status' => $lease->unit->status->value,
                'property' => $lease->unit->property ? [
                    'id' => $lease->unit->property->id,
                    'name' => $lease->unit->property->name,
                    'address' => $lease->unit->property->address,
                ] : null,
            ] : null,
        ];
    }

    private function rentStatus(?Lease $lease, ?Invoice $invoice): string
    {
        if (! $lease) {
            return 'none';
        }

        if (! $invoice) {
            return 'paid';
        }

        if ($invoice->isOverdue()) {
            return 'overdue';
        }

        if ($invoice->due_date->isToday()) {
            return 'due_today';
        }

        return $invoice->status === InvoiceStatus::Partial ? 'partial' : 'upcoming';
    }
}
