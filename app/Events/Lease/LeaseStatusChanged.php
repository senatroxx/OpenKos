<?php

namespace App\Events\Lease;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use Illuminate\Foundation\Events\Dispatchable;

class LeaseStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Lease $lease,
        public readonly LeaseStatus $from,
        public readonly LeaseStatus $to,
    ) {}
}
