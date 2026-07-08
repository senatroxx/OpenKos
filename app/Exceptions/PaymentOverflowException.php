<?php

namespace App\Exceptions;

use Exception;

class PaymentOverflowException extends Exception
{
    public function __construct()
    {
        parent::__construct('Payment exceeds outstanding balance.');
    }
}
