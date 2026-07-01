<?php

namespace App\Data\Lease;

final readonly class CreateLeaseData
{
    public function __construct(
        public array $tenantIds,
        public string $startDate,
        public ?string $endDate,
        public mixed $rentAmount,
        public ?int $billingInterval,
        public ?string $billingUnit,
        public ?int $roomRateId,
        public mixed $depositAmount,
        public ?string $depositPaidAt,
        public mixed $depositRefundAmount,
        public ?string $depositRefundedAt,
        public ?int $rentDueDay,
        public ?string $notes,
    ) {}
}
