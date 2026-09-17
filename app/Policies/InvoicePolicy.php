<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function pay(User $user, Invoice $invoice): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->hasTenantProfile()) {
            return $user->tenant()
                ->whereHas('leases', fn ($query) => $query->whereKey($invoice->lease_id))
                ->exists();
        }

        $propertyId = $invoice->lease?->property_id;

        return $user->can('payments.create')
            && $propertyId !== null
            && $user->canAccessProperty($propertyId);
    }
}
