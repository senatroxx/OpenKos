<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        Log::info('['.class_basename($notification).'] To: '.($notifiable->name ?? '—'));
    }
}
