<?php

namespace OpenKOS\Notification\Listeners;

use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Setting;
use App\Notifications\RentReminder;

class InvoiceReminderListener
{
    public function handle(InvoiceReminderDispatched $event): void
    {
        $lease = $event->event->lease;
        $lease->loadMissing('primaryTenant');
        $tenant = $lease->primaryTenant;

        $channels = Setting::get('reminder_channels') ?? ['log'];
        $hasContact = $tenant?->phone || ($tenant?->email && in_array('mail', $channels));

        if (! $hasContact) {
            return;
        }

        $tenant->notify(new RentReminder($event->event));
    }
}
