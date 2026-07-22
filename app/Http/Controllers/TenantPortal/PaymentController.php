<?php

namespace App\Http\Controllers\TenantPortal;

use App\Actions\Payments\RecordPayment;
use App\Data\Payment\RecordPaymentData;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Exceptions\PaymentOverflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreTenantPortalPaymentRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);

        $outstandingInvoices = $tenant->leases()
            ->active()
            ->with([
                'invoices' => fn ($query) => $query->payable()->orderBy('due_date'),
            ])
            ->latest('start_date')
            ->get()
            ->pluck('invoices')
            ->flatten()
            ->each->append(['outstanding', 'display_status']);

        $leaseIds = $tenant->leases()->select((new Lease)->qualifyColumn('id'));

        $invoiceHistory = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->latest('period_start')
            ->get()
            ->each->append(['outstanding', 'display_status']);

        $paymentQuery = Payment::query()
            ->whereHas('invoice', fn (Builder $query) => $query->whereIn(
                'lease_id',
                $leaseIds,
            ))
            ->with('invoice.lease.unit.property');

        return Inertia::render('tenant-portal/payments/index', [
            'outstandingInvoices' => $outstandingInvoices,
            'invoiceHistory' => $invoiceHistory,
            'pendingPayments' => (clone $paymentQuery)
                ->where('status', PaymentStatus::Pending)
                ->latest('payment_date')
                ->latest('id')
                ->get(),
            'paymentHistory' => (clone $paymentQuery)
                ->whereIn('status', [PaymentStatus::Confirmed, PaymentStatus::Cancelled])
                ->latest('payment_date')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): Response
    {
        $tenant = $this->tenant($request);

        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);

        $invoice->load(['lineItems', 'payments']);
        $invoice->append(['outstanding', 'display_status']);

        return Inertia::render('tenant-portal/payments/invoice', [
            'invoice' => $invoice,
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

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()->tenant()->first();

        abort_unless($tenant, 403);

        return $tenant;
    }
}
