<?php

namespace App\Http\Controllers\TenantPortal;

use App\Actions\Payments\RecordPayment;
use App\Data\Payment\RecordPaymentData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Exceptions\PaymentOverflowException;
use App\Http\Requests\Payment\StoreTenantPortalPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends TenantPortalController
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);
        $lease = $leaseContext['selectedLease'];
        $pendingPaymentAmount = Payment::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('invoice_id', (new Invoice)->qualifyColumn('id'))
            ->where('status', PaymentStatus::Pending);
        $pendingPaymentSql = $pendingPaymentAmount->toSql();
        $pendingPaymentBindings = $pendingPaymentAmount->getBindings();
        $outstandingSql = '(COALESCE(invoices.total, 0) - COALESCE(invoices.amount_paid, 0))';

        $actionableInvoices = Invoice::query()
            ->where('lease_id', $lease?->id)
            ->payable()
            ->whereRaw("{$outstandingSql} > ({$pendingPaymentSql})", $pendingPaymentBindings);

        $outstandingSummary = (clone $actionableInvoices)
            ->selectRaw(
                "COUNT(*) as count, COALESCE(SUM({$outstandingSql} - ({$pendingPaymentSql})), 0) as amount",
                $pendingPaymentBindings,
            )
            ->toBase()
            ->first();
        $nextDueDate = (clone $actionableInvoices)
            ->orderBy('due_date')
            ->first()?->due_date?->toDateString();

        $actionableInvoices = $actionableInvoices
            ->select('invoices.*')
            ->selectSub($pendingPaymentAmount, 'pending_payment_amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(5, pageName: 'action_page')
            ->through(fn (Invoice $invoice) => $invoice
                ->setAttribute(
                    'payable_amount',
                    number_format((float) $invoice->outstanding - (float) $invoice->pending_payment_amount, 2, '.', ''),
                )
                ->append(['outstanding', 'display_status']));

        $invoiceHistoryQuery = Invoice::query()
            ->where('lease_id', $lease?->id)
            ->whereNotIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value]);
        $invoiceHistoryCount = (clone $invoiceHistoryQuery)->count();
        $invoiceHistory = $invoiceHistoryQuery
            ->latest('period_start')
            ->limit(5)
            ->get()
            ->each->append(['outstanding', 'display_status']);

        $paymentQuery = Payment::query()
            ->whereHas('invoice', fn (Builder $query) => $query->where('lease_id', $lease?->id))
            ->with('invoice.lease.unit.property');
        $pendingPayments = (clone $paymentQuery)
            ->where('status', PaymentStatus::Pending)
            ->latest('payment_date')
            ->latest('id')
            ->get();
        $finalizedPaymentQuery = (clone $paymentQuery)
            ->whereIn('status', [PaymentStatus::Confirmed, PaymentStatus::Cancelled]);
        $finalizedPaymentCount = (clone $finalizedPaymentQuery)->count();

        return Inertia::render('tenant-portal/payments/index', [
            'actionableInvoices' => $actionableInvoices,
            'historicalInvoices' => $invoiceHistory,
            'historicalInvoiceCount' => $invoiceHistoryCount,
            'pendingPayments' => $pendingPayments,
            'finalizedPayments' => $finalizedPaymentQuery
                ->latest('payment_date')
                ->latest('id')
                ->limit(5)
                ->get(),
            'finalizedPaymentCount' => $finalizedPaymentCount,
            'outstandingSummary' => [
                'amount' => number_format((float) $outstandingSummary->amount, 2, '.', ''),
                'count' => (int) $outstandingSummary->count,
                'next_due_date' => $nextDueDate,
                'pending_payment_count' => $pendingPayments->count(),
            ],
            'leaseContext' => $this->leaseContextPayload(
                $lease,
                $leaseContext['leases'],
            ),
        ]);
    }

    public function invoiceHistory(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);
        $lease = $leaseContext['selectedLease'];

        $invoices = Invoice::query()
            ->where('lease_id', $lease?->id)
            ->whereNotIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Partial->value])
            ->latest('period_start')
            ->paginate(20, pageName: 'invoice_page')
            ->through(fn (Invoice $invoice) => $invoice->append(['outstanding', 'display_status']));

        return Inertia::render('tenant-portal/payments/invoice-history', [
            'invoices' => $invoices,
            'leaseContext' => $this->leaseContextPayload($lease, $leaseContext['leases']),
        ]);
    }

    public function paymentHistory(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $leaseContext = $this->leaseContext($request, $tenant);
        $lease = $leaseContext['selectedLease'];

        $payments = Payment::query()
            ->whereHas('invoice', fn (Builder $query) => $query->where('lease_id', $lease?->id))
            ->with('invoice.lease.unit.property')
            ->whereIn('status', [PaymentStatus::Confirmed, PaymentStatus::Cancelled])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(20, pageName: 'payment_page');

        return Inertia::render('tenant-portal/payments/payment-history', [
            'payments' => $payments,
            'leaseContext' => $this->leaseContextPayload($lease, $leaseContext['leases']),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): Response
    {
        $tenant = $this->tenant($request);
        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);

        $invoice->load(['lease.unit.property', 'lineItems', 'payments']);
        $invoice->append(['outstanding', 'display_status']);

        return Inertia::render('tenant-portal/payments/invoice', [
            'invoice' => $invoice,
            'lease' => [
                'reference' => $invoice->lease->reference,
                'unit_name' => $invoice->lease->unit?->name,
                'property_name' => $invoice->lease->unit?->property?->name,
            ],
        ]);
    }

    public function store(StoreTenantPortalPaymentRequest $request, RecordPayment $action): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $invoice = Invoice::with('lease')->findOrFail($request->integer('invoice_id'));
        $lease = $tenant->leases()->whereKey($invoice->lease_id)->firstOrFail();

        $request->ensureLeaseIsActive($lease);
        $request->ensureInvoiceIsPayable($invoice);

        $data = new RecordPaymentData(
            amount: (int) $request->amount,
            paymentDate: $request->paid_at,
            paymentMethod: $request->payment_method,
            notes: $request->notes,
            proof: $request->file('proof'),
        );

        try {
            $result = $action->execute($invoice, $data, $request->user(), forcePending: true);
        } catch (PaymentOverflowException) {
            abort(422, __('Payment exceeds the invoice outstanding balance.'));
        }

        if ($result->failed()) {
            abort(422, $result->error);
        }

        $payment = $result->payment;

        PaymentRecorded::dispatch($payment, actorId: $request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment submitted for verification.'),
        ]);

        return back();
    }
}
