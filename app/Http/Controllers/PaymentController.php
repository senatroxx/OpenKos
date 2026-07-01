<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Data\Payment\RecordPaymentData;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Lease $lease, RecordPayment $action): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $lease]);

        $request->ensureLeaseIsActive();
        $request->ensureNoDuplicatePayment($lease);

        $data = new RecordPaymentData(
            amount: (int) $request->amount,
            paymentDate: $request->paid_at,
            periodMonth: (int) $request->period_month,
            periodYear: (int) $request->period_year,
            paymentMethod: $request->payment_method,
            notes: $request->notes,
            proof: $request->file('proof'),
        );

        $result = $action->execute($lease, $data, $request->user());

        if ($result->failed()) {
            abort(422, $result->error);
        }

        $payment = $result->payment;

        $periodStart = sprintf('%04d-%02d-01', $request->period_year, $request->period_month);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment of :amount recorded for :period.', [
                'amount' => number_format((float) $payment->amount, 0, ',', '.'),
                'period' => date('F Y', strtotime($periodStart)),
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

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('verify', $payment);

        $request->validate([
            'action' => ['required', 'string', 'in:confirm,reject'],
        ]);

        $payment->update([
            'status' => $request->action === 'confirm' ? 'confirmed' : 'cancelled',
            'confirmed_by' => $request->user()->id,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

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
