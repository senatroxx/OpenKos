<?php

namespace App\Http\Controllers\TenantPortal;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Support\DateTimeFormatter;
use Brick\Math\BigDecimal;
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
            'currency' => $lease->currency,
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
                    'currency' => $invoice->currency,
                ],
                'pending_payment' => $pendingPayment ? [
                    'amount' => (string) $pendingPayment->amount,
                    'currency' => $pendingPayment->currency,
                    'payment_date' => $pendingPayment->payment_date->toDateString(),
                ] : null,
            ];
        }

        if ($pendingPayment) {
            return [
                'type' => 'payment_verification',
                'pending_payment' => [
                    'amount' => (string) $pendingPayment->amount,
                    'currency' => $pendingPayment->currency,
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
                'outstanding_amounts' => [],
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
        $summaryRows = (clone $actionableInvoices)
            ->select('invoices.*')
            ->selectSub($pendingPaymentAmount, 'pending_payment_amount')
            ->get();
        $outstandingAmounts = $summaryRows
            ->groupBy(fn (Invoice $invoice): string => $invoice->currency)
            ->map(function ($invoices, string $currency): array {
                $amount = $invoices->reduce(
                    fn (BigDecimal $total, Invoice $invoice): BigDecimal => $total
                        ->plus(BigDecimal::of($invoice->outstanding)->minus((string) $invoice->pending_payment_amount)),
                    BigDecimal::zero(),
                );

                return ['currency' => $currency, 'amount' => $amount->toString()];
            })
            ->values()
            ->all();

        return [
            'outstanding_amounts' => $outstandingAmounts,
            'payable_invoice_count' => $summaryRows->count(),
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
                'date' => DateTimeFormatter::format(
                    $payment->status === PaymentStatus::Pending
                        ? $payment->payment_date
                        : $payment->verified_at ?? $payment->updated_at,
                    'Y-m-d',
                ),
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->invoice?->reference,
            ]);
        $invoiceActivity = $lease->invoices()
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'reference', 'created_at'])
            ->map(fn (Invoice $invoice) => [
                'type' => 'invoice_issued',
                'date' => DateTimeFormatter::format($invoice->created_at, 'Y-m-d'),
                'amount' => null,
                'currency' => $invoice->currency,
                'reference' => $invoice->reference,
            ]);
        $leaseActivity = collect([[
            'type' => 'lease_started',
            'date' => $lease->start_date->toDateString(),
            'amount' => null,
            'currency' => $lease->currency,
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
