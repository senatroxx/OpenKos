<?php

namespace App\Events\Payment;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a payment is recorded. A domain event plugins can subscribe to
 * (e.g. accounting, analytics, notifications) via Plugin::listens().
 */
class PaymentRecorded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
