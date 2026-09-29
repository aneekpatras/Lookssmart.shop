<?php

namespace App\Listeners;

use App\Channels\MailChannel;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Brief §4 / Phase 7 item 8: "logged in notification_logs." A single global listener on Laravel's own
 * `NotificationSent` event covers every notification the app ever sends — not just booking
 * confirmations — so a future phase's notifications (reminders, reviews, etc.) are logged automatically
 * without each one remembering to do it itself.
 *
 * Failures aren't logged here as a separate `failed` row: a queued notification that throws goes to
 * Laravel's own `failed_jobs` table via the queue's normal retry mechanism (the "retryable" part of
 * the spec) — duplicating that into `notification_logs` would just be two sources of truth for the
 * same event.
 */
class LogNotificationDispatch
{
    public function handle(NotificationSent $event): void
    {
        // The `database` channel's own routeNotificationFor() returns a HasMany relation instance
        // (Laravel stores the notification via that relation, it isn't "routed" to an address like
        // mail/sms are) — there's no external recipient to log for it, so use the notifiable's own
        // identity instead of stringifying a relation object.
        $channel = $event->channel === MailChannel::class ? 'mail' : $event->channel;
        $recipient = $channel === 'database'
            ? ($event->notifiable->email ?? (($event->notifiable instanceof Model) ? $event->notifiable::class . '#' . $event->notifiable->getKey() : 'unknown'))
            : ($event->notifiable->routeNotificationFor($event->channel, $event->notification) ?? ($event->notifiable->email ?? 'unknown'));

        NotificationLog::create([
            'channel' => $channel,
            'recipient' => is_array($recipient) ? json_encode($recipient) : (string) $recipient,
            'notifiable_type' => $event->notifiable instanceof Model ? $event->notifiable::class : null,
            'notifiable_id' => $event->notifiable instanceof Model ? $event->notifiable->getKey() : null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
