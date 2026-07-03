<?php

namespace App\Results\Lease;

use App\Models\Lease;

final readonly class MoveOutLeaseResult
{
    public function __construct(
        public ?Lease $oldLease = null,
        public ?Lease $newLease = null,
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

    public static function success(Lease $oldLease, ?Lease $newLease = null): self
    {
        return new self(oldLease: $oldLease, newLease: $newLease);
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
