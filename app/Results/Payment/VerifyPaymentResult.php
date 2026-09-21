<?php

namespace App\Results\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;

final readonly class VerifyPaymentResult
{
    private function __construct(
        public ?Payment $payment = null,
        public ?PaymentStatus $oldStatus = null,
        public ?PaymentStatus $newStatus = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->payment !== null && $this->error === null;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public static function success(Payment $payment, PaymentStatus $oldStatus, PaymentStatus $newStatus): self
    {
        return new self(payment: $payment, oldStatus: $oldStatus, newStatus: $newStatus);
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
