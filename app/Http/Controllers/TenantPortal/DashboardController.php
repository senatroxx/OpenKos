<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
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

        return Inertia::render('tenant-portal/dashboard', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'lease' => $lease ? $this->dashboardLeasePayload($lease) : null,
            'nextAction' => $this->nextActionPayload($lease),
            'accountSummary' => $this->accountSummaryPayload($lease),
            'recentActivity' => $lease ? $this->recentActivityPayload($lease) : [],
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

    private function nextActionPayload(?Lease $lease): array
    {
        if (! $lease) {
            return ['type' => 'no_active_stay'];
        }

        $pendingPaymentAmount = Payment::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('invoice_id', (new Invoice)->qualifyColumn('id'))
            ->where('status', PaymentStatus::Pending);
        $pendingPaymentSql = $pendingPaymentAmount->toSql();
        $pendingPaymentBindings = $pendingPaymentAmount->getBindings();
        $outstandingSql = '(COALESCE(invoices.total, 0) - COALESCE(invoices.amount_paid, 0))';
        $invoice = $lease->invoices()
            ->payable()
            ->whereRaw("{$outstandingSql} > ({$pendingPaymentSql})", $pendingPaymentBindings)
            ->select('invoices.*')
            ->selectRaw("{$outstandingSql} - ({$pendingPaymentSql}) as payable_amount", $pendingPaymentBindings)
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();
        $pendingPayment = $lease->payments()
            ->where('payments.status', PaymentStatus::Pending)
            ->latest('payments.payment_date')
            ->latest('payments.id')
            ->first();

        if ($invoice) {
            return [
                'type' => 'payment_required',
                'invoice' => [
                    'id' => $invoice->id,
                    'reference' => $invoice->reference,
                    'due_date' => $invoice->due_date->toDateString(),
                    'display_status' => $invoice->display_status,
                    'amount' => (string) $invoice->payable_amount,
                ],
                'pending_payment' => $pendingPayment ? [
                    'amount' => (string) $pendingPayment->amount,
                    'payment_date' => $pendingPayment->payment_date->toDateString(),
                ] : null,
            ];
        }

        if ($pendingPayment) {
            return [
                'type' => 'payment_verification',
                'pending_payment' => [
                    'amount' => (string) $pendingPayment->amount,
                    'payment_date' => $pendingPayment->payment_date->toDateString(),
                ],
            ];
        }

        return ['type' => 'no_payment_required'];
    }

    private function accountSummaryPayload(?Lease $lease): array
    {
        if (! $lease) {
            return [
                'outstanding_balance' => '0',
                'payable_invoice_count' => 0,
                'pending_verification_count' => 0,
                'next_due_date' => null,
            ];
        }

        $invoice = new Invoice;
        $payment = new Payment;
        $pendingPaymentAmount = $payment->newQuery()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn(
                $payment->qualifyColumn('invoice_id'),
                $invoice->qualifyColumn('id'),
            )
            ->where($payment->qualifyColumn('status'), PaymentStatus::Pending);
        $pendingPaymentSql = $pendingPaymentAmount->toSql();
        $pendingPaymentBindings = $pendingPaymentAmount->getBindings();
        $outstandingSql = sprintf(
            '(COALESCE(%s, 0) - COALESCE(%s, 0))',
            $invoice->qualifyColumn('total'),
            $invoice->qualifyColumn('amount_paid'),
        );
        $actionableInvoices = $lease->invoices()
            ->payable()
            ->whereRaw("{$outstandingSql} > ({$pendingPaymentSql})", $pendingPaymentBindings);
        $summary = (clone $actionableInvoices)
            ->selectRaw(
                "COUNT(*) as payable_invoice_count, COALESCE(SUM({$outstandingSql} - ({$pendingPaymentSql})), 0) as outstanding_balance",
                $pendingPaymentBindings,
            )
            ->toBase()
            ->first();

        return [
            'outstanding_balance' => (string) $summary->outstanding_balance,
            'payable_invoice_count' => (int) $summary->payable_invoice_count,
            'pending_verification_count' => $lease->payments()
                ->where('payments.status', PaymentStatus::Pending)
                ->count(),
            'next_due_date' => (clone $actionableInvoices)
                ->orderBy($invoice->qualifyColumn('due_date'))
                ->value($invoice->qualifyColumn('due_date'))?->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivityPayload(Lease $lease): array
    {
        $paymentActivity = $lease->payments()
            ->with('invoice:id,reference')
            ->select('payments.*')
            ->latest('payments.updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment) => [
                'type' => match ($payment->status) {
                    PaymentStatus::Pending => 'payment_submitted',
                    PaymentStatus::Confirmed => 'payment_confirmed',
                    PaymentStatus::Cancelled => 'payment_cancelled',
                },
                'date' => ($payment->status === PaymentStatus::Pending
                    ? $payment->payment_date
                    : $payment->verified_at ?? $payment->updated_at)->toDateString(),
                'amount' => (string) $payment->amount,
                'reference' => $payment->invoice?->reference,
            ]);
        $invoiceActivity = $lease->invoices()
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'reference', 'created_at'])
            ->map(fn (Invoice $invoice) => [
                'type' => 'invoice_issued',
                'date' => $invoice->created_at->toDateString(),
                'amount' => null,
                'reference' => $invoice->reference,
            ]);
        $leaseActivity = collect([[
            'type' => 'lease_started',
            'date' => $lease->start_date->toDateString(),
            'amount' => null,
            'reference' => trim(implode(' · ', array_filter([
                $lease->unit?->name,
                $lease->unit?->property?->name,
            ]))),
        ]]);

        return $paymentActivity
            ->concat($invoiceActivity)
            ->concat($leaseActivity)
            ->sortByDesc('date')
            ->take(5)
            ->values()
            ->all();
    }
}
