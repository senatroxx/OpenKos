<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\AllocatePayment;
use App\Business\Payments\PaymentAttemptStatusValidator;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus as ApplicationPaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Results\Payment\ApplyGatewayPaymentResult as ApplyGatewayPaymentResultData;
use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Events\PaymentRecorded as PlatformPaymentRecorded;

class ApplyGatewayPaymentResult
{
    public function __construct(
        private AllocatePayment $allocatePayment,
        private MoneyConverter $money,
        private PaymentAttemptStatusValidator $statuses,
    ) {}

    public function execute(
        string $gatewayKey,
        PaymentProviderResult $result,
        string $source = 'webhook',
    ): ApplyGatewayPaymentResultData {
        $located = $this->locate($gatewayKey, $result);

        if ($located['status'] !== 'found') {
            $this->logAnomaly($gatewayKey, $result, $located['status'], source: $source);

            return new ApplyGatewayPaymentResultData(
                status: $located['status'] === 'unknown'
                    ? ApplyGatewayPaymentResultData::UNKNOWN
                    : ApplyGatewayPaymentResultData::ANOMALY,
            );
        }

        $processed = DB::transaction(function () use ($gatewayKey, $result, $located, $source): ApplyGatewayPaymentResultData {
            // Keep lock order aligned with payment initiation and manual payment recording.
            $invoice = Invoice::query()->lockForUpdate()->find($located['attempt']->invoice_id);

            if ($invoice === null) {
                return new ApplyGatewayPaymentResultData(ApplyGatewayPaymentResultData::UNKNOWN);
            }

            $locked = $this->locate($gatewayKey, $result);

            if ($locked['status'] !== 'found') {
                $this->logAnomaly($gatewayKey, $result, $locked['status'], source: $source);

                return new ApplyGatewayPaymentResultData(
                    status: $locked['status'] === 'unknown'
                        ? ApplyGatewayPaymentResultData::UNKNOWN
                        : ApplyGatewayPaymentResultData::ANOMALY,
                );
            }

            $attempt = PaymentAttempt::query()
                ->lockForUpdate()
                ->find($locked['attempt']->id);

            if ($attempt === null || $attempt->invoice_id !== $invoice->id) {
                return new ApplyGatewayPaymentResultData(ApplyGatewayPaymentResultData::UNKNOWN);
            }

            if ($reason = $this->invalidPaymentLinkReason($attempt)) {
                $this->logAnomaly($gatewayKey, $result, $reason, $attempt, $source);

                return new ApplyGatewayPaymentResultData(
                    status: ApplyGatewayPaymentResultData::ANOMALY,
                    attempt: $attempt,
                );
            }

            if ($reason = $this->invalidCallbackReason($attempt, $result)) {
                $this->logAnomaly($gatewayKey, $result, $reason, $attempt, $source);

                return new ApplyGatewayPaymentResultData(
                    status: ApplyGatewayPaymentResultData::ANOMALY,
                    attempt: $attempt,
                );
            }

            $canRecoverTerminalAttempt = $source === 'webhook'
                && $result->status === PaymentStatus::Settled
                && $attempt->payment_id === null
                && in_array($attempt->status, [
                    PaymentStatus::Failed,
                    PaymentStatus::Expired,
                    PaymentStatus::Canceled,
                ], true);

            if ($attempt->status !== PaymentStatus::Pending && ! $canRecoverTerminalAttempt) {
                return $this->applyTerminalCallback($gatewayKey, $attempt, $result, $source);
            }

            $metadata = $this->resultMetadata($attempt->metadata ?? [], $result, $source);

            if ($result->status !== PaymentStatus::Settled) {
                $timestampColumn = match ($result->status) {
                    PaymentStatus::Failed => 'failed_at',
                    PaymentStatus::Expired => 'expired_at',
                    PaymentStatus::Canceled => 'canceled_at',
                    PaymentStatus::Pending, PaymentStatus::Settled => null,
                };

                $updates = [
                    'provider_reference' => $attempt->provider_reference ?? $result->providerReference,
                    'metadata' => $metadata,
                ];

                if ($timestampColumn !== null) {
                    $this->statuses->validate($attempt->status, $result->status);
                    $updates['status'] = $result->status;
                    $updates[$timestampColumn] = $result->occurredAt ?? now();
                }

                $attempt->update($updates);

                return new ApplyGatewayPaymentResultData(
                    status: ApplyGatewayPaymentResultData::PROCESSED,
                    attempt: $attempt->fresh(),
                );
            }

            if ($this->cannotSettle($invoice, $attempt)) {
                $this->logAnomaly($gatewayKey, $result, 'invoice_balance_conflict', $attempt, $source);

                return new ApplyGatewayPaymentResultData(
                    status: ApplyGatewayPaymentResultData::ANOMALY,
                    attempt: $attempt,
                );
            }

            if ($attempt->status === PaymentStatus::Pending) {
                $this->statuses->validate($attempt->status, PaymentStatus::Settled);
            }

            $payment = $invoice->payments()->create([
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'payment_date' => ($result->occurredAt ?? now())->format('Y-m-d'),
                'payment_method' => PaymentMethod::Gateway->value,
                'reference_number' => $attempt->reference,
                'status' => ApplicationPaymentStatus::Confirmed,
                'verified_at' => now(),
            ]);

            $this->allocatePayment->execute($payment);

            $attempt->update([
                'provider_reference' => $attempt->provider_reference ?? $result->providerReference,
                'status' => PaymentStatus::Settled,
                'payment_id' => $payment->id,
                'settled_at' => $result->occurredAt ?? now(),
                'metadata' => $metadata,
            ]);

            return new ApplyGatewayPaymentResultData(
                status: ApplyGatewayPaymentResultData::PROCESSED,
                attempt: $attempt->fresh(),
                payment: $payment->fresh(),
            );
        });

        if ($processed->payment !== null) {
            PaymentRecorded::dispatch($processed->payment);
            event(new PlatformPaymentRecorded(paymentId: $processed->payment->id));
        }

        return $processed;
    }

    /**
     * @return array{status: 'found'|'unknown'|'conflict', attempt?: PaymentAttempt}
     */
    private function locate(string $gatewayKey, PaymentProviderResult $result): array
    {
        $byProviderReference = PaymentAttempt::query()
            ->where('gateway_key', $gatewayKey)
            ->where('provider_reference', $result->providerReference)
            ->first();

        $byReference = $result->reference === null
            ? null
            : PaymentAttempt::query()
                ->where('gateway_key', $gatewayKey)
                ->where('reference', $result->reference)
                ->first();

        if ($byProviderReference !== null && $byReference !== null && $byProviderReference->id !== $byReference->id) {
            return ['status' => 'conflict'];
        }

        $attempt = $byProviderReference ?? $byReference;

        if ($attempt === null) {
            return ['status' => 'unknown'];
        }

        if (
            ($result->reference !== null && $attempt->reference !== $result->reference)
            || ($attempt->provider_reference !== null && $attempt->provider_reference !== $result->providerReference)
        ) {
            return ['status' => 'conflict'];
        }

        return ['status' => 'found', 'attempt' => $attempt];
    }

    private function invalidCallbackReason(PaymentAttempt $attempt, PaymentProviderResult $result): ?string
    {
        if ($result->reference !== null && $result->reference !== $attempt->reference) {
            return 'openkos_reference_mismatch';
        }

        if ($attempt->provider_reference !== null && $attempt->provider_reference !== $result->providerReference) {
            return 'provider_reference_mismatch';
        }

        if ($result->amount === null) {
            return null;
        }

        $expected = $this->money->toMoney((string) $attempt->amount, $attempt->currency);

        return $expected->minorUnits === $result->amount->minorUnits
            && $expected->currency === $result->amount->currency
            ? null
            : 'amount_or_currency_mismatch';
    }

    private function invalidPaymentLinkReason(PaymentAttempt $attempt): ?string
    {
        if ($attempt->status !== PaymentStatus::Settled && $attempt->payment_id !== null) {
            return 'non_settled_attempt_with_payment';
        }

        if ($attempt->status !== PaymentStatus::Settled) {
            return null;
        }

        if ($attempt->payment_id === null) {
            return 'settled_attempt_without_payment';
        }

        $payment = $attempt->payment()->first();

        if ($payment === null) {
            return 'settled_attempt_with_missing_payment';
        }

        return $payment->invoice_id === $attempt->invoice_id
            ? null
            : 'settled_attempt_payment_invoice_mismatch';
    }

    private function cannotSettle(Invoice $invoice, PaymentAttempt $attempt): bool
    {
        if (in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            return true;
        }

        if ($invoice->currency !== $attempt->currency) {
            return true;
        }

        $outstanding = BigDecimal::of((string) $invoice->total)
            ->minus((string) $invoice->amount_paid);

        return $outstanding->compareTo(BigDecimal::of((string) $attempt->amount)) < 0;
    }

    private function applyTerminalCallback(
        string $gatewayKey,
        PaymentAttempt $attempt,
        PaymentProviderResult $result,
        string $source,
    ): ApplyGatewayPaymentResultData {
        if ($attempt->status === PaymentStatus::Settled && $result->status === PaymentStatus::Settled) {
            return new ApplyGatewayPaymentResultData(
                status: ApplyGatewayPaymentResultData::DUPLICATE,
                attempt: $attempt,
                payment: $attempt->payment()->first(),
            );
        }

        if ($attempt->status === $result->status) {
            return new ApplyGatewayPaymentResultData(
                status: ApplyGatewayPaymentResultData::DUPLICATE,
                attempt: $attempt,
            );
        }

        $this->logAnomaly($gatewayKey, $result, 'terminal_state_conflict', $attempt, $source);

        return new ApplyGatewayPaymentResultData(
            status: ApplyGatewayPaymentResultData::ANOMALY,
            attempt: $attempt,
        );
    }

    /**
     * @param  array<string, bool|int|string|null>  $metadata
     * @return array<string, bool|int|string|null>
     */
    private function resultMetadata(array $metadata, PaymentProviderResult $result, string $source): array
    {
        $prefix = $source === 'webhook' ? 'webhook_' : 'reconciliation_';

        foreach ($result->metadata as $key => $value) {
            $metadata[$prefix.$key] = $value;
        }

        if ($result->eventReference !== null) {
            $metadata[$prefix.'event_reference'] = $result->eventReference;
        }

        return $metadata;
    }

    private function logAnomaly(
        string $gatewayKey,
        PaymentProviderResult $result,
        string $reason,
        ?PaymentAttempt $attempt = null,
        string $source = 'webhook',
    ): void {
        Log::warning('Payment gateway result anomaly.', [
            'gateway' => $gatewayKey,
            'source' => $source,
            'attempt_id' => $attempt?->id,
            'attempt_status' => $attempt?->status->value,
            'provider_reference' => $result->providerReference,
            'openkos_reference' => $result->reference,
            'event_reference' => $result->eventReference,
            'provider_status' => $result->status->value,
            'reason' => $reason,
        ]);
    }
}
