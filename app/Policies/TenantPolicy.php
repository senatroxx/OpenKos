<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Enums\LeaseStatus;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->leases()
            ->whereHas('unit.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->leases()
            ->whereHas('unit.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        if ($tenant->leases()->where('status', LeaseStatus::Active)->exists()) {
            return false;
        }

        return $tenant->leases()
            ->whereHas('unit.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function assignUnit(User $user, Unit $unit): bool
    {
        return $user->properties->contains($unit->property_id);
    }
}
