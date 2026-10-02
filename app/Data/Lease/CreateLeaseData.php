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
        public ?int $propertyRateId = null,
        public ?int $unitTypeRateId = null,
    ) {}

    /** @param array<int, int> $tenantIds */
    public function withTenantIds(array $tenantIds): self
    {
        return new self(
            tenantIds: $tenantIds,
            startDate: $this->startDate,
            endDate: $this->endDate,
            rentAmount: $this->rentAmount,
            billingInterval: $this->billingInterval,
            billingUnit: $this->billingUnit,
            billingStrategy: $this->billingStrategy,
            unitRateId: $this->unitRateId,
            depositAmount: $this->depositAmount,
            depositPaidAt: $this->depositPaidAt,
            depositRefundAmount: $this->depositRefundAmount,
            depositRefundedAt: $this->depositRefundedAt,
            rentDueDay: $this->rentDueDay,
            notes: $this->notes,
            propertyRateId: $this->propertyRateId,
            unitTypeRateId: $this->unitTypeRateId,
        );
    }

    public function withStartDate(string $startDate): self
    {
        return new self(
            tenantIds: $this->tenantIds,
            startDate: $startDate,
            endDate: $this->endDate,
            rentAmount: $this->rentAmount,
            billingInterval: $this->billingInterval,
            billingUnit: $this->billingUnit,
            billingStrategy: $this->billingStrategy,
            unitRateId: $this->unitRateId,
            depositAmount: $this->depositAmount,
            depositPaidAt: $this->depositPaidAt,
            depositRefundAmount: $this->depositRefundAmount,
            depositRefundedAt: $this->depositRefundedAt,
            rentDueDay: $this->rentDueDay,
            notes: $this->notes,
            propertyRateId: $this->propertyRateId,
            unitTypeRateId: $this->unitTypeRateId,
        );
    }
}
