<?php

namespace App\Data\Dashboard;

final readonly class RentLeaseData
{
    public function __construct(
        public int $id,
        public int $rentDueDay,
        public string $currency,
        public string $rentAmount,
        public bool $hasPayment,
        public string $tenantName,
        public string $targetType,
        public string $unitName,
        public string $propertyName,
    ) {}
}
