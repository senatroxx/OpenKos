<?php

namespace App\Actions\Payments;

use App\Data\Payment\RecordPaymentData;
use App\Events\PaymentRecorded;
use App\Models\Lease;
use App\Models\User;
use App\Results\Payment\RecordPaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPayment
{
    public function execute(Lease $lease, RecordPaymentData $data, User $user): RecordPaymentResult
    {
        $periodStart = sprintf('%04d-%02d-01', $data->periodYear, $data->periodMonth);
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $hasProof = $data->proof !== null;
        $canAutoVerify = $hasProof && $user->can('payments.verify');

        $payment = DB::transaction(function () use ($lease, $data, $periodStart, $periodEnd, $user, $hasProof, $canAutoVerify) {
            $payment = $lease->payments()->create([
                'amount' => $data->amount,
                'payment_date' => $data->paymentDate,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_method' => $data->paymentMethod,
                'notes' => $data->notes,
                'status' => $hasProof && ! $canAutoVerify ? 'pending' : 'confirmed',
                'confirmed_by' => $canAutoVerify || ! $hasProof ? $user->id : null,
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

            return $payment;
        });

        $payment->load('confirmedBy:id,name', 'recordedBy:id,name', 'proofs');

        PaymentRecorded::dispatch($payment);

        return RecordPaymentResult::success($payment);
    }
}
