<?php

namespace App\Results\Lease;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Unit;

final readonly class TerminateLeaseResult
{
    public function __construct(
        public ?Lease $lease = null,
        public ?Unit $unit = null,
        public ?LeaseStatus $oldLeaseStatus = null,
        public ?UnitStatus $oldUnitStatus = null,
        public ?UnitStatus $newUnitStatus = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->lease !== null && $this->error === null;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public static function success(Lease $lease, LeaseStatus $oldLeaseStatus, ?Unit $unit, ?UnitStatus $oldUnitStatus, ?UnitStatus $newUnitStatus): self
    {
        return new self($lease, $unit, $oldLeaseStatus, $oldUnitStatus, $newUnitStatus);
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
