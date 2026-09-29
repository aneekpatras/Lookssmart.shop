<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousLoginDetected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $ipAddress,
        private readonly string $userAgent,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your account')
            ->line("We noticed a new sign-in to your account from an IP address we haven't seen before.")
            ->line("IP address: {$this->ipAddress}")
            ->line("Device: {$this->userAgent}")
            ->line('If this was you, no action is needed.')
            ->action('Review active sessions', url('/user/sessions'))
            ->line('If this was not you, please change your password immediately.');
    }
}
