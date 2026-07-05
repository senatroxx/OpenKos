<?php

namespace App\Business\Leases;

use App\Enums\LeaseStatus;
use App\Exceptions\LeaseRenewalException;
use App\Models\Lease;

class RenewalEligibilityChecker
{
    public function canRenew(Lease $lease): bool
    {
        return $lease->status === LeaseStatus::Active
            && $lease->end_date !== null;
    }

    public function ensureCanRenew(Lease $lease): void
    {
        if (! $this->canRenew($lease)) {
            throw new LeaseRenewalException(
                $lease->end_date === null
                    ? 'Open-ended leases cannot be renewed.'
                    : 'Only active leases can be renewed.',
            );
        }
    }
}
