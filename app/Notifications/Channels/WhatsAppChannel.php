<?php

namespace App\Notifications\Channels;

use App\Notifications\RentReminder;
use App\Services\WhatsAppManager;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        /** @var RentReminder $notification */
        $this->whatsapp->send(
            $notifiable->routeNotificationForWhatsApp($notification),
            $notification->toWhatsApp($notifiable),
        );
    }
}
