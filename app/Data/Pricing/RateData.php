<?php

namespace App\Data\Pricing;

final readonly class RateData
{
    public function __construct(
        public int $id,
        public int $billingInterval,
        public string $billingUnit,
        public string $amount,
        public string $currency,
        public string $source,
    ) {}

    public function identity(): string
    {
        return implode('|', [$this->billingInterval, $this->billingUnit, $this->currency]);
    }
}
