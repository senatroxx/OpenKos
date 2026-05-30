<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->leases()
            ->whereHas('room.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->leases()
            ->whereHas('room.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $tenant->leases()
            ->whereHas('room.property.users', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public function assignRoom(User $user, Room $room): bool
    {
        return $user->properties->contains($room->property_id);
    }
}
