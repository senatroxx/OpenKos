<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Lease $lease): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $lease]);

        $request->ensureLeaseIsActive();
        $request->ensureNoDuplicatePayment($lease);

        $periodStart = sprintf('%04d-%02d-01', $request->period_year, $request->period_month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $user = $request->user();
        $hasProof = $request->hasFile('proof');
        $canAutoVerify = $hasProof && $user->can('payments.verify');

        $payment = DB::transaction(function () use ($request, $lease, $periodStart, $periodEnd, $user, $hasProof, $canAutoVerify) {
            $payment = $lease->payments()->create([
                'amount' => $request->amount,
                'payment_date' => $request->paid_at,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'status' => $hasProof && ! $canAutoVerify ? 'pending' : 'confirmed',
                'confirmed_by' => $canAutoVerify || ! $hasProof ? $user->id : null,
                'recorded_by' => $user->id,
                'verified_by' => $canAutoVerify ? $user->id : null,
                'verified_at' => $canAutoVerify ? now() : null,
            ]);

            if ($hasProof) {
                $this->storeProof($request, $payment);
            }

            return $payment;
        });

        $payment->load('confirmedBy:id,name', 'recordedBy:id,name', 'proofs');

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

    private function storeProof(StorePaymentRequest $request, Payment $payment): void
    {
        $file = $request->file('proof');
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
}
