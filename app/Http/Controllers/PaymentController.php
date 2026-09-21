<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\VerifyPayment;
use App\Data\Payment\RecordPaymentData;
use App\Data\Payment\VerifyPaymentData;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentRecorded;
use App\Events\Payment\PaymentStatusChanged;
use App\Exceptions\PaymentOverflowException;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Media;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use OpenKOS\Core\Events\PaymentRecorded as PlatformPaymentRecorded;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Lease $lease, RecordPayment $action): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $lease]);

        $request->ensureLeaseIsActive();

        $invoice = Invoice::findOrFail($request->invoice_id);
        $request->ensureInvoiceIsPayable($invoice);

        $data = new RecordPaymentData(
            amount: (string) $request->amount,
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
        event(new PlatformPaymentRecorded(paymentId: $payment->getKey(), actorId: Auth::id()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment of :amount recorded for :period.', [
                'amount' => $payment->amount.' '.$payment->currency,
                'period' => $invoice->period_start->format('F Y'),
            ]),
        ]);

        return back();
    }

    public function proof(Payment $payment, PaymentProof $proof): StreamedResponse
    {
        $this->authorize('view', $payment);
        abort_if($proof->payment_id !== $payment->id, 404);

        if ($proof->media_id !== null) {
            $media = $this->canonicalMedia($payment, $proof);

            $storage = Storage::disk($media->disk);
            abort_unless($storage->exists($media->path), 404);

            return $storage->response($media->path, $media->original_name, [
                'Content-Type' => $media->mime_type,
            ]);
        }

        $storage = Storage::disk('local');
        abort_unless($storage->exists($proof->path), 404);

        return $storage->response($proof->path, $proof->original_name, [
            'Content-Type' => $proof->mime_type,
        ]);
    }

    private function canonicalMedia(Payment $payment, PaymentProof $proof): Media
    {
        $media = $proof->media;

        abort_if(
            $media === null
                || $media->mediable_type !== $payment->getMorphClass()
                || (string) $media->mediable_id !== (string) $proof->payment_id
                || $media->collection !== 'proofs',
            404,
        );

        return $media;
    }

    public function __construct(
        private VerifyPayment $verifyPayment,
    ) {}

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('verify', $payment);

        $request->validate([
            'action' => ['required', 'string', 'in:confirm,reject'],
        ]);

        $newStatus = $request->action === 'confirm' ? PaymentStatus::Confirmed : PaymentStatus::Cancelled;
        $result = $this->verifyPayment->execute($payment, new VerifyPaymentData($newStatus, $request->user()->id));

        if ($result->failed()) {
            abort(422, $result->error);
        }

        PaymentStatusChanged::dispatch($result->payment, $result->oldStatus, $result->newStatus, actorId: Auth::id());

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
