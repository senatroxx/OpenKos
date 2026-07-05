<?php

namespace App\Business\Payments;

use App\Enums\PaymentStatus;

class PaymentStatusValidator
{
    public function validate(PaymentStatus $current, PaymentStatus $next): void
    {
        $allowed = match ($current) {
            PaymentStatus::Pending => [PaymentStatus::Confirmed, PaymentStatus::Cancelled],
            default => [],
        };

        abort_unless(in_array($next, $allowed), 422, __('Cannot transition from :current to :next.', [
            'current' => $current->label(),
            'next' => $next->label(),
        ]));
    }
}
