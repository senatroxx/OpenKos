<?php

namespace App\Results\Payment;

use App\Models\Payment;

final readonly class RecordPaymentResult
{
    public function __construct(
        public ?Payment $payment = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->payment !== null;
    }

    public function failed(): bool
    {
        return $this->payment === null;
    }

    public static function success(Payment $payment): self
    {
        return new self(payment: $payment);
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
