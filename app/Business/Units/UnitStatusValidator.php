<?php

namespace App\Business\Units;

use App\Enums\UnitStatus;

class UnitStatusValidator
{
    public function validate(UnitStatus $current, UnitStatus $next): void
    {
        $allowed = match ($current) {
            UnitStatus::Available => [UnitStatus::Occupied, UnitStatus::Maintenance],
            UnitStatus::Occupied => [UnitStatus::Maintenance, UnitStatus::Available],
            UnitStatus::Maintenance => [UnitStatus::Available, UnitStatus::Occupied],
            default => [],
        };

        abort_unless(in_array($next, $allowed), 422, __('Cannot transition unit from :current to :next.', [
            'current' => $current->label(),
            'next' => $next->label(),
        ]));
    }
}
