<?php

namespace App\Data\Payment;

use Illuminate\Http\UploadedFile;

final readonly class RecordPaymentData
{
    public function __construct(
        public int $amount,
        public string $paymentDate,
        public string $paymentMethod,
        public ?string $notes = null,
        public ?UploadedFile $proof = null,
    ) {}
}
