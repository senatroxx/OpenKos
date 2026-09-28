<?php

namespace App\Business\Applications;

use App\Enums\ApplicationStatus;

final class ApplicationStatusValidator
{
    public function canTransition(ApplicationStatus $current, ApplicationStatus $next): bool
    {
        return match ($current) {
            ApplicationStatus::New => in_array($next, [ApplicationStatus::Reviewing, ApplicationStatus::Withdrawn], true),
            ApplicationStatus::Reviewing => in_array($next, [ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn], true),
            ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn => false,
        };
    }
}
