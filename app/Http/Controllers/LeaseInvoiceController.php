<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentAttempt;
use App\Models\Setting;
use App\Services\Invoices\InvoicePdfArtifact;
use App\Services\Localization\ApplicationLocale;
use App\Services\Payments\SignedInvoicePaymentLink;
use App\Support\DateTimeFormatter;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Core\Enums\PaymentStatus as GatewayPaymentStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaseInvoiceController extends Controller
{
    public function index(Request $request, Lease $lease): Response
    {
        $this->authorize('view', $lease);

        $table = Table::make()
            ->columns([
                Column::make('reference', 'Reference')->searchable(fn (Builder $q, string $search) => $q->where('reference', 'like', "%{$search}%")),
                Column::make('period_start', 'Period')->sortable(),
                Column::make('due_date', 'Due date')->sortable(),
                Column::make('total', 'Total')->sortable(),
                Column::make('amount_paid', 'Paid')->sortable(),
                Column::make('outstanding', 'Outstanding'),
                Column::make('status', 'Status')->sortable(),
            ])
            ->filters([
                Filter::select('status', 'Status', ['pending', 'partial', 'paid', 'cancelled', 'void'])
                    ->query(fn (Builder $q, string $value) => $q->where('status', $value)),
            ])
            ->defaultSort('-period_start');

        $result = $table->paginate(
            $lease->invoices()->select('invoices.*')->selectRaw('(COALESCE(total, 0) - COALESCE(amount_paid, 0)) as outstanding'),
            $request,
            'invoices',
        );

        $result['invoices']->getCollection()->each->append('display_status');

        return Inertia::render('leases/invoices', [
            ...$result,
            'lease' => $lease->only('id', 'reference', 'status'),
        ]);
    }

    public function show(
        Lease $lease,
        Invoice $invoice,
        InvoicePdfArtifact $artifact,
        SignedInvoicePaymentLink $paymentLinks,
    ): Response {
        abort_if($invoice->lease_id !== $lease->id, 404);

        $this->authorize('view', $lease);

        $invoice->loadMissing('lease.unit');
        $invoice->load(['lineItems', 'payments.confirmedBy:id,name', 'payments.proofs.media']);
        $gatewayAttempts = $invoice->paymentAttempts()
            ->latest('id')
            ->get([
                'id',
                'invoice_id',
                'gateway_key',
                'reference',
                'provider_reference',
                'amount',
                'currency',
                'status',
                'expires_at',
                'initiated_at',
                'created_at',
                'updated_at',
            ])
            ->map(fn (PaymentAttempt $attempt): array => [
                'id' => $attempt->id,
                'invoice_id' => $attempt->invoice_id,
                'gateway' => $attempt->gateway_key,
                'reference' => $attempt->reference,
                'provider_reference' => $attempt->provider_reference,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'status' => $attempt->status->value,
                'expires_at' => DateTimeFormatter::nullableIso($attempt->expires_at),
                'initiated_at' => DateTimeFormatter::iso($attempt->initiated_at),
                'created_at' => DateTimeFormatter::iso($attempt->created_at),
                'updated_at' => DateTimeFormatter::iso($attempt->updated_at),
                'failure_code' => $this->failureCode($attempt),
                'failure_message' => $this->failureMessage($attempt),
                'recheckable' => $attempt->status === GatewayPaymentStatus::Pending
                    && $attempt->provider_reference !== null,
            ])
            ->values();
        $invoice->append(['outstanding', 'display_status']);
        $invoicePdfStatus = $artifact->status($invoice);
        if ($invoicePdfStatus === 'pending') {
            $artifact->ensureQueued($invoice);
        }

        $paymentLink = in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true)
            && Gate::allows('pay', $invoice)
            ? $paymentLinks->url($invoice)
            : null;

        return Inertia::render('leases/invoice-detail', [
            'lease' => $lease->only('id', 'reference', 'status'),
            'invoice' => $invoice,
            'invoicePdf' => ['status' => $invoicePdfStatus],
            'paymentLink' => $paymentLink,
            'gatewayAttempts' => $gatewayAttempts,
        ]);
    }

    private function failureCode(PaymentAttempt $attempt): ?string
    {
        return match ($attempt->status) {
            GatewayPaymentStatus::Failed => 'provider_failed',
            GatewayPaymentStatus::Expired => 'checkout_expired',
            GatewayPaymentStatus::Canceled => 'checkout_canceled',
            default => null,
        };
    }

    private function failureMessage(PaymentAttempt $attempt): ?string
    {
        return match ($attempt->status) {
            GatewayPaymentStatus::Failed => __('The payment provider reported a failure.'),
            GatewayPaymentStatus::Expired => __('The checkout session expired before payment was completed.'),
            GatewayPaymentStatus::Canceled => __('The checkout session was canceled.'),
            default => null,
        };
    }

    public function print(Lease $lease, Invoice $invoice, ApplicationLocale $locale): ViewContract
    {
        abort_if($invoice->lease_id !== $lease->id, 404);

        $this->authorize('view', $lease);

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

    public function download(Lease $lease, Invoice $invoice, InvoicePdfArtifact $artifact): StreamedResponse|RedirectResponse
    {
        abort_if($invoice->lease_id !== $lease->id, 404);

        $this->authorize('view', $lease);

        if ($artifact->status($invoice) !== 'available') {
            $artifact->ensureQueued($invoice);

            return redirect()->route('leases.workspace.invoices.show', [$lease, $invoice]);
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
}
