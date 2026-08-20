<?php

namespace App\Events\Maintenance;

use App\Models\MaintenanceTicket;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class MaintenanceResolved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly MaintenanceTicket $ticket,
        public readonly ?int $actorId = null,
    ) {}
}
