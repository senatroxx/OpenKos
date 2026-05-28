<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are invited to OpenKos'))
            ->greeting(__('Welcome to OpenKos'))
            ->line(__('You have been invited to manage properties in OpenKos.'))
            ->line(__('Accept the invitation to set your password and activate your account.'))
            ->action(__('Accept Invitation'), $this->url)
            ->line(__('If you did not expect this invitation, you may ignore this email.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['url' => $this->url];
    }
}
