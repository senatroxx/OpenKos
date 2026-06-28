<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function view(User $user, Lease $lease): bool
    {
        return $user->properties->contains($lease->room->property_id);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function update(User $user, Lease $lease): bool
    {
        return $user->properties->contains($lease->room->property_id);
    }

    public function delete(User $user, Lease $lease): bool
    {
        return $user->properties->contains($lease->room->property_id);
    }

    public function move(User $user, Lease $lease, Room $targetRoom): bool
    {
        return $user->properties->contains($lease->room->property_id)
            && $user->properties->contains($targetRoom->property_id);
    }

    public function renew(User $user, Lease $lease): bool
    {
        return $user->properties->contains($lease->room->property_id);
    }

    public function moveOut(User $user, Lease $lease, ?Room $targetRoom = null): bool
    {
        if (! $user->properties->contains($lease->room->property_id)) {
            return false;
        }

        if ($targetRoom && ! $user->properties->contains($targetRoom->property_id)) {
            return false;
        }

        return true;
    }

    public function sendReminder(User $user, Lease $lease): bool
    {
        return $user->hasPermissionTo('reminders.send')
            && $user->properties->contains($lease->room->property_id);
    }
}
