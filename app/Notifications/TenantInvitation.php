<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.tenant_invitation.subject'))
            ->greeting(__('notifications.tenant_invitation.greeting'))
            ->line(__('notifications.tenant_invitation.intro'))
            ->line(__('notifications.tenant_invitation.instruction'))
            ->action(__('notifications.tenant_invitation.action'), $this->url)
            ->line(__('notifications.tenant_invitation.outro'));
    }

    public function toArray(object $notifiable): array
    {
        return ['url' => $this->url];
    }
}
