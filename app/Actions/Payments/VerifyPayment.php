<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\AllocatePayment;
use App\Business\Payments\PaymentStatusValidator;
use App\Data\Payment\VerifyPaymentData;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Results\Payment\VerifyPaymentResult;
use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class VerifyPayment
{
    public function __construct(
        private PaymentStatusValidator $paymentStatusValidator,
        private AllocatePayment $allocatePayment,
        private MoneyConverter $moneyConverter,
    ) {}

    public function execute(Payment $payment, VerifyPaymentData $data): VerifyPaymentResult
    {
        $oldStatus = $payment->status;
        $this->paymentStatusValidator->validate($oldStatus, $data->status);

        return DB::transaction(function () use ($payment, $data, $oldStatus): VerifyPaymentResult {
            $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);
            $lockedPayment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                return VerifyPaymentResult::error(__('Payment has already been verified.'));
            }

            if ($data->status === PaymentStatus::Confirmed) {
                $confirmedSum = (string) $invoice->payments()
                    ->where('status', PaymentStatus::Confirmed->value)
                    ->sum('amount');

                if ($this->moneyConverter->compare(
                    BigDecimal::of($confirmedSum)->plus((string) $lockedPayment->amount)->toString(),
                    (string) $invoice->total,
                ) > 0) {
                    return VerifyPaymentResult::error(__('Confirming this payment would exceed the invoice total.'));
                }

                $lockedPayment->update([
                    'status' => $data->status,
                    'confirmed_by' => $data->verifiedBy,
                    'verified_by' => $data->verifiedBy,
                    'verified_at' => now(),
                ]);

                $this->allocatePayment->execute($lockedPayment);
            } else {
                $affectedInvoiceIds = $lockedPayment->allocations()
                    ->pluck('invoice_id')
                    ->push($lockedPayment->invoice_id)
                    ->unique()
                    ->values();

                $lockedPayment->allocations()->delete();
                $lockedPayment->update([
                    'status' => $data->status,
                    'confirmed_by' => null,
                    'verified_by' => $data->verifiedBy,
                    'verified_at' => now(),
                ]);

                $affectedInvoices = Invoice::whereIn('id', $affectedInvoiceIds)
                    ->lockForUpdate()
                    ->get();

                Invoice::recalculateStatuses($affectedInvoices);
            }

            return VerifyPaymentResult::success($lockedPayment->refresh(), $oldStatus, $data->status);
        });
    }
}
