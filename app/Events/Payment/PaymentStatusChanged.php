<?php

namespace App\Events\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentStatus $from,
        public readonly PaymentStatus $to,
        public readonly ?int $actorId = null,
    ) {}
}
