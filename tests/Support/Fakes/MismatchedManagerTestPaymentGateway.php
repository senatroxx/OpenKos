<?php

namespace Tests\Support\Fakes;

class MismatchedManagerTestPaymentGateway extends ManagerTestPaymentGateway
{
    public function key(): string
    {
        return 'provider/gateway';
    }
}
