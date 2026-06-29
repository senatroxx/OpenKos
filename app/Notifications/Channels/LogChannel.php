<?php

namespace App\Notifications\Channels;

use App\Notifications\RentReminder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        /** @var RentReminder $notification */
        Log::channel('whatsapp')->info('[Reminder] To: ' . ($notifiable->phone ?? $notifiable->email ?? '—') . ' — ' . $notification->toWhatsApp($notifiable));
    }
}
