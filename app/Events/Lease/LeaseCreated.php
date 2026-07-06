<?php

namespace App\Events\Lease;

use App\Models\Lease;
use Illuminate\Foundation\Events\Dispatchable;

class LeaseCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Lease $lease,
        public readonly array $tenantIds,
    ) {}
}
