<?php

namespace App\Data\Payment;

use App\Enums\PaymentStatus;

final readonly class VerifyPaymentData
{
    public function __construct(
        public PaymentStatus $status,
        public int $verifiedBy,
    ) {}
}
