<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Support\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brief §4 / Phase 8 items 2-3: "review request" — sent after a completed booking. Template only
 * this sub-step; the trigger (a `review_request` `ReminderRule`) is sub-step 2's job. The actual
 * review-submission form is Phase 11 (CRM) — this only links to the public site, which doesn't have
 * that form yet either; the link target gets wired up when Phase 11 builds it.
 */
class ReviewRequest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int, string> $channels */
    public function __construct(
        private readonly Booking $booking,
        private readonly array $channels = ['email', 'sms', 'whatsapp'],
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return array_values(array_filter([
            in_array('email', $this->channels, true) ? MailChannel::class : null,
            in_array('sms', $this->channels, true) && filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? SmsChannel::class : null,
            in_array('whatsapp', $this->channels, true) && filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? WhatsappChannel::class : null,
            'database',
        ]));
    }

    public function toSms(mixed $notifiable): string
    {
        return 'We would love your feedback for booking ' . $this->booking->code . '. Share your experience with us. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
    }

    public function toWhatsapp(mixed $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('items.service');
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $customerName = $booking->customer?->name ?? $booking->guest_name ?? 'there';

        return (new MailMessage)
            ->subject('How was your visit?')
            ->view('mail.review-request', [
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'cta_url' => url('/reviews/new?booking=' . $booking->code),
                'cta_label' => 'Leave a review',
                'title' => 'We’d Love Your Feedback',
                'subtitle' => 'Your experience helps us keep growing and improving.',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'message' => 'How was your visit? Leave a review.',
        ];
    }
}
