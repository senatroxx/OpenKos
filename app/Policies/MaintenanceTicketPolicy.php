<?php

namespace App\Policies;

use App\Models\MaintenanceTicket;
use App\Models\User;

class MaintenanceTicketPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->properties->contains($ticket->property_id);
    }

    public function update(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->properties->contains($ticket->property_id);
    }

    public function delete(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->properties->contains($ticket->property_id);
    }

    public function assign(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->properties->contains($ticket->property_id);
    }
}
