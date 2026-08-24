<?php

namespace App\Listeners;

use App\Events\Maintenance\MaintenanceTicketCreated;
use App\Events\Maintenance\MaintenanceTicketUpdated;
use App\Models\MaintenanceTicket;
use App\Notifications\TenantPortalNotification;
use App\Services\Localization\ApplicationLocale;

class NotifyMaintenanceTenant
{
    public function __construct(private ApplicationLocale $locale) {}

    public function handleCreated(MaintenanceTicketCreated $event): void
    {
        $this->notify($event->ticket, 'maintenance_created');
    }

    public function handleUpdated(MaintenanceTicketUpdated $event): void
    {
        $this->notify($event->ticket, 'maintenance_updated');
    }

    private function notify(MaintenanceTicket $ticket, string $type): void
    {
        $notificationLocale = $this->locale->apply();
        $tenant = $ticket->creator?->tenant;
        if (! $tenant) {
            return;
        }

        $tenant->notify((new TenantPortalNotification([
            'type' => $type,
            'title' => __('Maintenance update'),
            'message' => $ticket->title,
            'url' => route('portal.maintenance-tickets.show', $ticket),
        ]))->locale($notificationLocale));
    }
}
