<?php

namespace App\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;

final class PaymentGatewayCurrencyUnsupportedException extends RuntimeException
{
    public function __construct(string $gatewayKey, public readonly string $currency)
    {
        parent::__construct(sprintf(
            '%s is not available for %s payments.',
            Str::headline($gatewayKey),
            $currency,
        ));
    }
}
