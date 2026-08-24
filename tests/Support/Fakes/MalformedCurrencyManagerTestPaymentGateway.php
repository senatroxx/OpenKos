<?php

namespace Tests\Support\Fakes;

class MalformedCurrencyManagerTestPaymentGateway extends CurrencyAwareManagerTestPaymentGateway
{
    public function key(): string
    {
        return 'malformed-currency';
    }

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array
    {
        return ['IDR', 'not-a-currency'];
    }
}
