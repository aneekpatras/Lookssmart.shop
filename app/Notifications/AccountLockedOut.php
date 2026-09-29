<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedOut extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account was temporarily locked')
            ->line('We locked your account for a short time after several failed sign-in attempts.')
            ->line('If this wasn\'t you, please change your password once you regain access.')
            ->line('You can try signing in again in a few minutes.');
    }
}
