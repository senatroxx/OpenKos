<?php

namespace App\Events\Maintenance;

use App\Models\MaintenanceTicket;
use Illuminate\Foundation\Events\Dispatchable;

class MaintenanceResolved
{
    use Dispatchable;

    public function __construct(
        public readonly MaintenanceTicket $ticket,
        public readonly ?int $actorId = null,
    ) {}
}
