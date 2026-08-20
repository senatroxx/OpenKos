<?php

namespace App\Http\Controllers;

use App\Actions\Payments\StartGatewayPayment;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\PaymentGatewayCreationException;
use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SignedInvoicePaymentLink;
use App\Support\DateTimeFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Core\Enums\PaymentStatus as GatewayPaymentStatus;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SignedPaymentController extends Controller
{
    public function show(
        Request $request,
        string $token,
        SignedInvoicePaymentLink $paymentLinks,
        PaymentGatewayManager $gateways,
    ): Response {
        $invoice = $this->invoice($request, $token, $paymentLinks);
        $invoice->load(['lineItems', 'lease.unit.property', 'lease.primaryTenant']);
        $invoice->append(['outstanding', 'display_status']);

        $gatewayAttempts = $this->gatewayAttempts($invoice);
        $hasResumableAttempt = $gatewayAttempts->contains(
            fn (PaymentAttempt $attempt): bool => $attempt->resumable,
        );

        return Inertia::render('payments/signed-invoice', [
            'invoice' => [
                'reference' => $invoice->reference,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'status' => $invoice->status->value,
                'display_status' => $invoice->display_status,
                'total' => (string) $invoice->total,
                'amount_paid' => (string) $invoice->amount_paid,
                'outstanding' => $invoice->outstanding,
                'context' => [
                    'property_name' => $invoice->lease?->unit?->property?->name,
                    'unit_name' => $invoice->lease?->unit?->name,
                    'tenant_name' => $this->maskedTenantName($invoice->lease?->primaryTenant?->name),
                ],
                'line_items' => $invoice->lineItems->map(fn ($item): array => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'description' => $item->description,
                    'amount' => (string) $item->amount,
                ])->values()->all(),
            ],
            'gatewayAttempts' => $gatewayAttempts->map(fn (PaymentAttempt $attempt): array => [
                'id' => $attempt->id,
                'invoice_id' => $attempt->invoice_id,
                'amount' => (string) $attempt->amount,
                'currency' => $attempt->currency,
                'status' => $attempt->status->value,
                'expires_at' => DateTimeFormatter::nullableIso($attempt->expires_at),
                'resumable' => $attempt->resumable,
                'checkout_instructions' => $attempt->checkout_instructions,
                'initiated_at' => DateTimeFormatter::iso($attempt->initiated_at),
            ])->values()->all(),
            'onlinePaymentAvailable' => $this->isPayable($invoice)
                && ($hasResumableAttempt || $gateways->active() !== null),
            'paymentUrl' => $request->fullUrl(),
            'csrfToken' => csrf_token(),
        ]);
    }

    public function pay(
        Request $request,
        string $token,
        SignedInvoicePaymentLink $paymentLinks,
        StartGatewayPayment $action,
    ): SymfonyResponse {
        $invoice = $this->invoice($request, $token, $paymentLinks);

        try {
            $result = $action->executeViaSignedLink($invoice);
        } catch (InvoiceNotPayableException|PaymentGatewayUnavailableException) {
            return $this->gatewayPaymentError($request, __('Online payment is not available for this invoice.'));
        } catch (PaymentGatewayCreationException $exception) {
            return $this->gatewayPaymentError(
                $request,
                $exception->ambiguous
                    ? __('Checkout creation is still being confirmed. Refresh this invoice before trying again.')
                    : __('Online payment could not be started. Please try again.'),
            );
        }

        if ($result->instructions->url !== null) {
            return $request->header('X-Inertia')
                ? Inertia::location($result->instructions->url)
                : redirect()->away($result->instructions->url);
        }

        if ($result->instructions->entries !== []) {
            return redirect()->to($request->fullUrl());
        }

        return $this->gatewayPaymentError(
            $request,
            __('Online payment instructions were not returned. Please try again.'),
        );
    }

    private function invoice(
        Request $request,
        string $token,
        SignedInvoicePaymentLink $paymentLinks,
    ): Invoice {
        abort_unless($request->hasValidSignatureWhileIgnoring(['status']), 403);

        return $paymentLinks->resolve($token);
    }

    /**
     * @return Collection<int, PaymentAttempt>
     */
    private function gatewayAttempts(Invoice $invoice): Collection
    {
        return $invoice->paymentAttempts()
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
            ])
            ->each(function (PaymentAttempt $attempt): void {
                $attempt->setAttribute(
                    'resumable',
                    $attempt->status === GatewayPaymentStatus::Pending
                        && ($attempt->expires_at === null || $attempt->expires_at->isFuture())
                        && $this->hasUsableInstructions($attempt->checkout_instructions),
                );
            });
    }

    private function isPayable(Invoice $invoice): bool
    {
        return in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Partial], true);
    }

    private function hasUsableInstructions(?array $instructions): bool
    {
        return ($instructions['url'] ?? null) !== null
            || ($instructions['entries'] ?? []) !== [];
    }

    private function maskedTenantName(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim($name ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return null;
        }

        return implode(' ', array_map(
            static fn (string $part): string => mb_substr($part, 0, 1).'***',
            $parts,
        ));
    }

    private function gatewayPaymentError(Request $request, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return redirect()->to($request->fullUrl());
    }
}
