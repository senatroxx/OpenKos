<?php

namespace App\Business\Leases;

use App\Enums\LeaseStatus;

class LeaseStatusValidator
{
    public function validate(LeaseStatus $current, LeaseStatus $next): void
    {
        $allowed = match ($current) {
            LeaseStatus::Active => [LeaseStatus::Terminated, LeaseStatus::Renewed],
            default => [],
        };

        abort_unless(in_array($next, $allowed), 422, __('Cannot transition from :current to :next.', [
            'current' => $current->label(),
            'next' => $next->label(),
        ]));
    }
}
