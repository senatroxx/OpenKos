<?php

namespace App\Data\Lease;

final readonly class CreateLeaseData
{
    public function __construct(
        public array $tenantIds,
        public string $startDate,
        public ?string $endDate,
        public ?string $rentAmount,
        public ?int $billingInterval,
        public ?string $billingUnit,
        public ?string $billingStrategy,
        public ?int $unitRateId,
        public ?string $depositAmount,
        public ?string $depositPaidAt,
        public ?string $depositRefundAmount,
        public ?string $depositRefundedAt,
        public ?int $rentDueDay,
        public ?string $notes,
    ) {}
}
