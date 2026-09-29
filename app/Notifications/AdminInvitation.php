<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an email address that doesn't have a User row yet (Phase 5 sub-step 4 invite flow), so
 * this is dispatched via `Notification::route('mail', $email)->notify(...)`, not `$user->notify()`.
 */
class AdminInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $inviterName, private readonly string $signedUrl) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->inviterName} invited you to Looks Smart Beauty Salon")
            ->line("{$this->inviterName} has invited you to the Looks Smart Beauty Salon admin dashboard.")
            ->action('Accept invitation', $this->signedUrl)
            ->line('This invitation link expires in 7 days.');
    }
}
