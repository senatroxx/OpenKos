<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Business\Payments\PaymentStatusValidator;
use App\Data\Payment\RecordPaymentData;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Events\Payment\PaymentStatusChanged;
use App\Exceptions\PaymentOverflowException;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Lease $lease, RecordPayment $action): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $lease]);

        $request->ensureLeaseIsActive();

        $invoice = Invoice::findOrFail($request->invoice_id);
        $request->ensureInvoiceIsPayable($invoice);

        $data = new RecordPaymentData(
            amount: (int) $request->amount,
            paymentDate: $request->paid_at,
            paymentMethod: $request->payment_method,
            notes: $request->notes,
            proof: $request->file('proof'),
        );

        try {
            $result = $action->execute($invoice, $data, $request->user());
        } catch (PaymentOverflowException) {
            abort(422, __('Payment exceeds the invoice outstanding balance.'));
        }

        if ($result->failed()) {
            abort(422, $result->error);
        }

        $payment = $result->payment;

        PaymentRecorded::dispatch($payment, actorId: Auth::id());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment of :amount recorded for :period.', [
                'amount' => number_format((float) $payment->amount, 0, ',', '.'),
                'period' => $invoice->period_start->format('F Y'),
            ]),
        ]);

        return back();
    }

    public function proof(Payment $payment, PaymentProof $proof)
    {
        $this->authorize('view', $payment);

        $path = storage_path('app/private/'.$proof->path);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function __construct(
        private PaymentStatusValidator $paymentStatusValidator,
    ) {}

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('verify', $payment);

        $request->validate([
            'action' => ['required', 'string', 'in:confirm,reject'],
        ]);

        $newStatus = $request->action === 'confirm' ? PaymentStatus::Confirmed : PaymentStatus::Cancelled;
        $oldStatus = $payment->status;

        $this->paymentStatusValidator->validate($oldStatus, $newStatus);

        if ($newStatus === PaymentStatus::Confirmed) {
            DB::transaction(function () use ($payment, $request, $newStatus) {
                $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);

                $confirmedSum = (float) $invoice->payments()
                    ->where('status', PaymentStatus::Confirmed->value)
                    ->sum('amount');

                if ($confirmedSum + (float) $payment->amount > (float) $invoice->total) {
                    abort(422, 'Confirming this payment would exceed the invoice total.');
                }

                $payment->update([
                    'status' => $newStatus,
                    'confirmed_by' => $request->user()->id,
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                ]);

                $invoice->recalculateStatus();
            });

            $payment->refresh();
        } else {
            $payment->update([
                'status' => $newStatus,
                'confirmed_by' => $request->user()->id,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            $payment->invoice->recalculateStatus();
        }

        PaymentStatusChanged::dispatch($payment, $oldStatus, $newStatus, actorId: Auth::id());

        $message = $request->action === 'confirm'
            ? __('Payment verified successfully.')
            : __('Payment rejected.');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }
}
