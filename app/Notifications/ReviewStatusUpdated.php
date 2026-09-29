<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Models\Review;
use App\Support\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 11 sub-step 3: notifies a registered customer when their review is approved or when the
 * salon posts a public reply. Unlike Phase 11 sub-step 2's `MessageReply` (an anonymous contact-form
 * sender), `Review.customer_id` is always a real registered `User` when present, so this goes through
 * the app's own preference-aware `MailChannel`/`NotificationPreferenceService` like every other
 * customer-facing notification — not on-demand routing. Never dispatched for guest reviews
 * (`customer_id` null) or on rejection (Brief/spec only calls for a notification on
 * approval/reply, not rejection).
 */
class ReviewStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Review $review,
        private readonly string $event,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return [MailChannel::class, 'database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $isReply = $this->event === 'replied';

        return (new MailMessage)
            ->subject($isReply ? 'We replied to your review' : 'Your review is now live')
            ->view('mail.review-status', [
                'customer_name' => $this->review->customer?->name ?? 'there',
                'is_reply' => $isReply,
                'rating' => $this->review->rating,
                'review_body' => $this->review->body,
                'admin_reply' => $this->review->admin_reply,
                'title' => $isReply ? 'We replied to your review' : 'Your review is now live',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'event' => $this->event,
            'message' => $this->event === 'replied' ? 'The salon replied to your review.' : 'Your review was approved and is now public.',
        ];
    }
}
