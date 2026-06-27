<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
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

        $payment = DB::transaction(function () use ($request, $lease, $periodStart, $periodEnd) {
            $payment = $lease->payments()->create([
                'amount' => $request->amount,
                'payment_date' => $request->paid_at,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'status' => 'confirmed',
                'confirmed_by' => $request->user()->id,
                'recorded_by' => $request->user()->id,
            ]);

            if ($request->hasFile('proof')) {
                $file = $request->file('proof');
                $extension = $file->getClientOriginalExtension();
                $path = $file->storeAs(
                    'payment-proofs/'.$payment->id,
                    (string) Str::uuid().'.'.$extension,
                    'local',
                );

                $payment->update(['proof_path' => $path]);
            }

            return $payment;
        });

        $payment->load('confirmedBy:id,name', 'recordedBy:id,name');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment of :amount recorded for :period.', [
                'amount' => number_format((float) $payment->amount, 0, ',', '.'),
                'period' => date('F Y', strtotime($periodStart)),
            ]),
        ]);

        return to_route('dashboard');
    }
}
