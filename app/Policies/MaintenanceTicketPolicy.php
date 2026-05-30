<?php

namespace App\Policies;

use App\Models\MaintenanceTicket;
use App\Models\User;

class MaintenanceTicketPolicy
{
    public function view(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->properties->contains($ticket->room->property_id);
    }
}
