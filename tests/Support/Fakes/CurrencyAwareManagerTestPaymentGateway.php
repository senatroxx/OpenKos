<?php

namespace Tests\Support\Fakes;

use OpenKOS\Core\Contracts\PaymentGatewayCurrencySupport;

class CurrencyAwareManagerTestPaymentGateway extends ManagerTestPaymentGateway implements PaymentGatewayCurrencySupport
{
    public function key(): string
    {
        return 'currency-aware';
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper(trim($currency)), $this->supportedCurrencies(), true);
    }

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array
    {
        return ['IDR'];
    }
}
