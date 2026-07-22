<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\AllocatePayment;
use App\Data\Payment\RecordPaymentData;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentOverflowException;
use App\Models\Invoice;
use App\Models\User;
use App\Results\Payment\RecordPaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPayment
{
    public function __construct(
        private AllocatePayment $allocatePayment,
    ) {}

    public function execute(Invoice $invoice, RecordPaymentData $data, User $user, bool $forcePending = false): RecordPaymentResult
    {
        $hasProof = $data->proof !== null;
        $canAutoVerify = ! $forcePending && $hasProof && $user->can('payments.verify');

        $payment = DB::transaction(function () use ($invoice, $data, $user, $hasProof, $canAutoVerify, $forcePending) {
            // Ponytail: lock the invoice so concurrent payments see the
            // correct outstanding balance and cannot overpay.
            $locked = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $availableAmount = (float) $locked->outstanding;

            if ($forcePending) {
                $availableAmount -= (float) $locked->payments()
                    ->where('status', PaymentStatus::Pending)
                    ->sum('amount');
            }

            if ($data->amount > $availableAmount) {
                throw new PaymentOverflowException;
            }

            $payment = $locked->payments()->create([
                'amount' => $data->amount,
                'payment_date' => $data->paymentDate,
                'payment_method' => $data->paymentMethod,
                'notes' => $data->notes,
                'status' => $forcePending || ($hasProof && ! $canAutoVerify) ? PaymentStatus::Pending : PaymentStatus::Confirmed,
                'confirmed_by' => ! $forcePending && ($canAutoVerify || ! $hasProof) ? $user->id : null,
                'recorded_by' => $user->id,
                'verified_by' => $canAutoVerify ? $user->id : null,
                'verified_at' => $canAutoVerify ? now() : null,
            ]);

            if ($hasProof) {
                $file = $data->proof;
                $extension = $file->getClientOriginalExtension();
                $path = $file->storeAs(
                    'payment-proofs/'.$payment->id,
                    (string) Str::uuid().'.'.$extension,
                    'local',
                );

                $payment->proofs()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]);
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
