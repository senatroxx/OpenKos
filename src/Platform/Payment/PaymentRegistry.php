<?php

namespace OpenKOS\Platform\Payment;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\PaymentGateway;

class PaymentRegistry implements Arrayable
{
    /** @var array<string, class-string<PaymentGateway>|PaymentGateway> */
    private array $gateways = [];

    /**
     * @param  class-string<PaymentGateway>|PaymentGateway  $gateway
     */
    public function registerGateway(string $key, string|PaymentGateway $gateway): static
    {
        if (isset($this->gateways[$key])) {
            throw new InvalidArgumentException("Payment gateway [{$key}] is already registered.");
        }

        $this->gateways[$key] = $gateway;

        return $this;
    }

    /**
     * @return array<string, class-string<PaymentGateway>|PaymentGateway>
     */
    public function gateways(): array
    {
        return $this->gateways;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    public function toArray(): array
    {
        return array_keys($this->gateways);
    }
}
