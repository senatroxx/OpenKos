<?php

namespace App\Data\Lease;

final readonly class TerminateLeaseData
{
    public function __construct(public ?string $reason = null) {}
}
