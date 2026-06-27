<?php

namespace App\Data\Lease;

use App\Enums\DepositHandling;
use Carbon\CarbonImmutable;

final readonly class RenewLeaseData
{
    public function __construct(
        public CarbonImmutable $endDate,
        public int $rentAmount,
        public DepositHandling $depositHandling,
        public bool $confirmedOutstanding,
    ) {}
}
