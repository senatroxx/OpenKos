<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = method_exists($notification, 'toLog') ? $notification->toLog($notifiable) : '';

        Log::channel('reminders')->info('['.class_basename($notification).'] To: '.($notifiable->name ?? '—').' — '.$message);
    }
}
