<?php

namespace App\Events\Unit;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class UnitStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Unit $unit,
        public readonly UnitStatus $from,
        public readonly UnitStatus $to,
        public readonly ?int $actorId = null,
    ) {}
}
