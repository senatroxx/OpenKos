<?php

namespace App\Data\Lease;

final readonly class MoveOutLeaseData
{
    public function __construct(
        public string $terminationDate,
        public string $endDate,
        public string $reason,
        public bool $depositReturned = false,
        public ?int $depositRefundAmount = null,
        public ?string $notes = null,
        public bool $moveToAnotherRoom = false,
        public ?int $targetRoomId = null,
        public bool $carryDepositRefund = false,
    ) {}
}
