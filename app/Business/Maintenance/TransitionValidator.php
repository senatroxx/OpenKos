<?php

namespace App\Business\Maintenance;

use App\Enums\MaintenanceStatus;

class TransitionValidator
{
    public function validate(MaintenanceStatus $current, MaintenanceStatus $next): void
    {
        $allowed = match ($current) {
            MaintenanceStatus::Reported => [MaintenanceStatus::InProgress, MaintenanceStatus::Cancelled],
            MaintenanceStatus::InProgress => [MaintenanceStatus::Resolved, MaintenanceStatus::Cancelled],
            default => [],
        };

        abort_unless(in_array($next, $allowed), 422, __('Cannot transition from :current to :next.', [
            'current' => $current->label(),
            'next' => $next->label(),
        ]));
    }
}
