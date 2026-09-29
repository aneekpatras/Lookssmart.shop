<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 11 sub-step 2: an admin-composed reply to a public contact message. Sent via on-demand
 * routing (`Notification::route('mail', $message->email)->notify(...)`) rather than the app's
 * preference-aware `MailChannel`/`NotificationPreferenceService` — a contact-form sender isn't a
 * customer with notification preferences to check, just an email address an admin is replying to.
 * Uses Laravel's stock `mail` channel for that reason, not the custom channel every other
 * notification in this app goes through.
 */
class MessageReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Message $message,
        private readonly string $replyBody,
        private readonly string $repliedByName,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Re: ' . ($this->message->subject ?: 'Your message to us'))
            ->view('mail.message-reply', [
                'customer_name' => $this->message->name,
                'original_body' => $this->message->body,
                'reply_body' => $this->replyBody,
                'replied_by' => $this->repliedByName,
                'title' => 'We replied to your message',
            ]);
    }
}
