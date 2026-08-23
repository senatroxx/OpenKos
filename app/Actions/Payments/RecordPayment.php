<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\AllocatePayment;
use App\Data\Payment\RecordPaymentData;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentOverflowException;
use App\Models\Invoice;
use App\Models\User;
use App\Results\Payment\RecordPaymentResult;
use App\Services\Media\MediaManager;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function __construct(
        private AllocatePayment $allocatePayment,
        private MediaManager $mediaManager,
    ) {}

    public function execute(Invoice $invoice, RecordPaymentData $data, User $user, bool $forcePending = false): RecordPaymentResult
    {
        $hasProof = $data->proof !== null;
        $canAutoVerify = ! $forcePending && $hasProof && $user->can('payments.verify');

        $payment = DB::transaction(function () use ($invoice, $data, $user, $hasProof, $canAutoVerify, $forcePending) {
            // Ponytail: lock the invoice so concurrent payments see the
            // correct outstanding balance and cannot overpay.
            $locked = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $availableAmount = BigDecimal::of($locked->outstanding);

            if ($forcePending) {
                $availableAmount = $availableAmount->minus((string) $locked->payments()
                    ->where('status', PaymentStatus::Pending)
                    ->sum('amount'));
            }

            if (BigDecimal::of($data->amount)->compareTo($availableAmount) > 0) {
                throw new PaymentOverflowException;
            }

            $payment = $locked->payments()->create([
                'amount' => $data->amount,
                'currency' => $locked->currency,
                'payment_date' => $data->paymentDate,
                'payment_method' => $data->paymentMethod,
                'notes' => $data->notes,
                'status' => $forcePending || ($hasProof && ! $canAutoVerify) ? PaymentStatus::Pending : PaymentStatus::Confirmed,
                'confirmed_by' => ! $forcePending && ($canAutoVerify || ! $hasProof) ? $user->id : null,
                'recorded_by' => $user->id,
                'verified_by' => ! $forcePending && ($canAutoVerify || ! $hasProof) ? $user->id : null,
                'verified_at' => ! $forcePending && ($canAutoVerify || ! $hasProof) ? now() : null,
            ]);

            if ($hasProof) {
                $file = $data->proof;
                $media = $this->mediaManager->store($payment, 'proofs', $file);

                $proof = $payment->proofs()->make([
                    'media_id' => $media->id,
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                ]);

                // Deprecated compatibility field; canonical storage is Media.
                $proof->forceFill(['path' => $media->path])->saveOrFail();
            }

            if ($payment->status === PaymentStatus::Confirmed) {
                $this->allocatePayment->execute($payment);
            }

            return $payment;
        });

        $payment->load('confirmedBy:id,name', 'recordedBy:id,name', 'proofs');

        return RecordPaymentResult::success($payment);

        // ponytail: PaymentOverflowException is caught by PaymentController
        // since it extends the base Handler's renderable exceptions path — no
        // custom handler needed for one exception.
    }
}
