<?php

namespace App\Listeners;

use App\Events\Reminder\InvoiceReminderDispatched;
use App\Models\Setting;
use App\Notifications\RentReminder;
use App\Services\Localization\ApplicationLocale;

class SendInvoiceReminder
{
    public function __construct(private ApplicationLocale $locale) {}

    public function handle(InvoiceReminderDispatched $event): void
    {
        $notificationLocale = $this->locale->apply();
        $lease = $event->event->lease;
        $lease->loadMissing('primaryTenant.user');
        $tenant = $lease->primaryTenant;

        $channels = Setting::get('reminder_channels') ?? ['log'];

        if (! $tenant?->hasReminderRoute($channels)) {
            return;
        }

        $tenant->notify((new RentReminder($event->event))->locale($notificationLocale));
    }
}
