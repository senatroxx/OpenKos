<?php

namespace App\Results\Lease;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Unit;

final readonly class MoveOutLeaseResult
{
    public function __construct(
        public ?Lease $oldLease = null,
        public ?Lease $newLease = null,
        public ?Unit $sourceUnit = null,
        public ?Unit $targetUnit = null,
        public ?LeaseStatus $oldLeaseStatus = null,
        public ?UnitStatus $oldSourceStatus = null,
        public ?UnitStatus $newSourceStatus = null,
        public ?UnitStatus $oldTargetStatus = null,
        public ?UnitStatus $newTargetStatus = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->oldLease !== null && $this->error === null;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public static function success(
        Lease $oldLease,
        ?Lease $newLease = null,
        ?Unit $sourceUnit = null,
        ?Unit $targetUnit = null,
        ?LeaseStatus $oldLeaseStatus = null,
        ?UnitStatus $oldSourceStatus = null,
        ?UnitStatus $newSourceStatus = null,
        ?UnitStatus $oldTargetStatus = null,
        ?UnitStatus $newTargetStatus = null,
    ): self {
        return new self(
            oldLease: $oldLease,
            newLease: $newLease,
            sourceUnit: $sourceUnit,
            targetUnit: $targetUnit,
            oldLeaseStatus: $oldLeaseStatus,
            oldSourceStatus: $oldSourceStatus,
            newSourceStatus: $newSourceStatus,
            oldTargetStatus: $oldTargetStatus,
            newTargetStatus: $newTargetStatus,
        );
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
