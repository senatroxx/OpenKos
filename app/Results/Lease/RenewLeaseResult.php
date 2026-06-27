<?php

namespace App\Results\Lease;

use App\Models\Lease;

final readonly class RenewLeaseResult
{
    public function __construct(
        public ?Lease $newLease = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->newLease !== null;
    }

    public function failed(): bool
    {
        return $this->newLease === null;
    }

    public static function success(Lease $newLease): self
    {
        return new self(newLease: $newLease);
    }

    public static function error(string $error): self
    {
        return new self(error: $error);
    }
}
