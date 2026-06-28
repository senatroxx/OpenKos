<?php

namespace App\Notifications;

use App\Data\Reminder\ReminderEvent;
use App\Models\Setting;
use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ReminderEvent $event) {}

    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $days = $this->event->overdueDays
            ?? (int) now()->startOfDay()->diffInDays(Carbon::parse($this->event->dueDate), false);

        $amount = number_format($this->event->amount / 100, 0);
        $date = Carbon::parse($this->event->dueDate)->format('d M Y');

        $template = Setting::get()->reminder_message_template;

        if ($template) {
            return str_replace(
                [':name', ':room', ':days', ':amount', ':date'],
                [$notifiable->name, $this->event->lease->room?->name ?? '—', $days, $amount, $date],
                $template,
            );
        }

        return __("notifications.rent.{$this->event->type->value}", [
            'name' => $notifiable->name,
            'room' => $this->event->lease->room?->name ?? '—',
            'days' => $days,
            'amount' => $amount,
            'date' => $date,
        ]);
    }
}
