<?php

namespace App\Http\Controllers\TenantPortal;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\StartGatewayPayment;
use App\Data\Payment\RecordPaymentData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\PaymentGatewayCreationException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Exceptions\PaymentOverflowException;
use App\Http\Requests\Payment\StoreTenantPortalPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Setting;
use App\Services\Invoices\InvoicePdfArtifact;
use App\Services\Localization\ApplicationLocale;
use App\Services\Payments\MoneyConverter;
use App\Services\Payments\PaymentGatewayManager;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Core\Enums\PaymentStatus as GatewayPaymentStatus;
use OpenKOS\Core\Events\PaymentRecorded as PlatformPaymentRecorded;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends TenantPortalController
{
    public function __construct(private MoneyConverter $money) {}

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

        $outstandingSummaryRows = (clone $actionableInvoices)
            ->select('invoices.*')
            ->selectSub($pendingPaymentAmount, 'pending_payment_amount')
            ->get();
        $outstandingAmounts = $outstandingSummaryRows
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
                    BigDecimal::of($invoice->outstanding)
                        ->minus((string) $invoice->pending_payment_amount)
                        ->toString(),
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
                'amounts' => $outstandingAmounts ?: [[
                    'currency' => $lease?->currency ?? $this->money->normalizeCurrency(),
                    'amount' => '0',
                ]],
                'count' => $outstandingSummaryRows->count(),
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

    public function invoice(
        Request $request,
        Invoice $invoice,
        InvoicePdfArtifact $artifact,
        PaymentGatewayManager $gateways,
    ): Response {
        $this->ensureTenantOwnsInvoice($request, $invoice);

        $invoice->load(['lease.unit.property', 'lineItems', 'payments']);
        $gatewayAttempts = $invoice->paymentAttempts()
            ->latest('id')
            ->get([
                'id',
                'invoice_id',
                'amount',
                'currency',
                'status',
                'expires_at',
                'checkout_instructions',
                'initiated_at',
            ]);
        $gatewayAttempts->each(function (PaymentAttempt $attempt): void {
            $attempt->setAttribute(
                'resumable',
                $attempt->status === GatewayPaymentStatus::Pending
                    && ($attempt->expires_at === null || $attempt->expires_at->isFuture())
                    && $this->hasUsableInstructions($attempt->checkout_instructions),
            );
        });
        $invoice->append(['outstanding', 'display_status']);
        $invoicePdfStatus = $artifact->status($invoice);
        if ($invoicePdfStatus === 'pending') {
            $artifact->ensureQueued($invoice);
        }

        $hasResumableAttempt = $gatewayAttempts->contains(
            fn (PaymentAttempt $attempt): bool => $attempt->resumable,
        );

        return Inertia::render('tenant-portal/payments/invoice', [
            'invoice' => $invoice,
            'invoicePdf' => ['status' => $invoicePdfStatus],
            'gatewayAttempts' => $gatewayAttempts,
            'onlinePaymentAvailable' => $hasResumableAttempt || $gateways->active() !== null,
            'lease' => [
                'reference' => $invoice->lease->reference,
                'unit_name' => $invoice->lease->unit?->name,
                'property_name' => $invoice->lease->unit?->property?->name,
            ],
        ]);
    }

    public function pay(Request $request, Invoice $invoice, StartGatewayPayment $action): SymfonyResponse
    {
        $this->ensureTenantOwnsInvoice($request, $invoice);

        try {
            $result = $action->execute($invoice, $request->user());
        } catch (InvoiceNotPayableException|PaymentGatewayUnavailableException) {
            return $this->gatewayPaymentError(__('Online payment is not available for this invoice.'));
        } catch (PaymentGatewayCreationException $exception) {
            return $this->gatewayPaymentError(
                $exception->ambiguous
                    ? __('Checkout creation is still being confirmed. Refresh this invoice before trying again.')
                    : __('Online payment could not be started. Please try again.'),
            );
        }

        if ($result->instructions->url !== null) {
            return Inertia::location($result->instructions->url);
        }

        if ($result->instructions->entries !== []) {
            return to_route('portal.billing.invoices.show', $invoice);
        }

        return $this->gatewayPaymentError(__('Online payment instructions were not returned. Please try again.'));
    }

    public function download(Request $request, Invoice $invoice, InvoicePdfArtifact $artifact): StreamedResponse|RedirectResponse
    {
        $this->ensureTenantOwnsInvoice($request, $invoice);

        if ($artifact->status($invoice) !== 'available') {
            $artifact->ensureQueued($invoice);

            return redirect()->route('portal.billing.invoices.show', $invoice);
        }

        $reference = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $invoice->reference ?? (string) $invoice->getKey(),
        );

        return Storage::disk('local')->download(
            $artifact->path($invoice),
            "invoice-{$reference}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function print(Request $request, Invoice $invoice, ApplicationLocale $locale): ViewContract
    {
        $this->ensureTenantOwnsInvoice($request, $invoice);

        $invoice->load([
            'lease.primaryTenant.user',
            'lease.unit.property.city',
            'lease.unit.property.region',
            'lineItems',
            'payments' => fn ($query) => $query
                ->where('status', PaymentStatus::Confirmed)
                ->orderBy('payment_date')
                ->orderBy('id'),
        ]);
        $invoice->append(['outstanding', 'display_status']);
        $settings = Setting::some(['site_name', 'locale', 'currency']);
        $resolvedLocale = $locale->resolve($settings['locale'] ?? null);

        return view('invoices.pdf', [
            'autoPrint' => true,
            'currency' => $invoice->currency,
            'invoice' => $invoice,
            'locale' => $resolvedLocale,
            'siteName' => $settings['site_name'] ?? config('app.name'),
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
            amount: (string) $request->amount,
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
        event(new PlatformPaymentRecorded(paymentId: $payment->getKey(), actorId: $request->user()->id));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment submitted for verification.'),
        ]);

        return back();
    }

    private function ensureTenantOwnsInvoice(Request $request, Invoice $invoice): void
    {
        $tenant = $this->tenant($request);

        abort_unless($tenant->leases()->whereKey($invoice->lease_id)->exists(), 404);
    }

    private function gatewayPaymentError(string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return back();
    }

    private function hasUsableInstructions(?array $instructions): bool
    {
        return ($instructions['url'] ?? null) !== null
            || ($instructions['entries'] ?? []) !== [];
    }
}
