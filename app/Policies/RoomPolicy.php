<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function view(User $user, Room $room): bool
    {
        return $user->properties->contains($room->property_id);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->properties->contains($property);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->properties->contains($room->property_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->properties->contains($room->property_id);
    }
}
