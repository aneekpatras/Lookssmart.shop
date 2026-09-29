<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Support\NotificationPreferenceService;
use App\Support\SalonTimezone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brief §4 / Phase 8 items 2-3: "reminder" branded email — template built here; the actual trigger
 * (an active `ReminderRule` matched by `SendDueRemindersJob`) is Phase 8 sub-step 2's job.
 */
class BookingReminder extends Notification implements ShouldQueue
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
        return 'Reminder: your appointment for ' . $this->booking->code . ' is on ' . $this->booking->starts_at->format('D, M j, Y') . ' at ' . $this->booking->starts_at->format('g:i A') . '. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
    }

    public function toWhatsapp(mixed $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['items.service']);
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $customerName = $booking->customer?->name ?? $booking->guest_name ?? 'there';
        $localStart = $booking->starts_at->clone()->setTimezone(SalonTimezone::DISPLAY);

        return (new MailMessage)
            ->subject("Reminder: your booking is coming up — {$booking->code}")
            ->view('mail.booking-reminder', [
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'cta_url' => url('/book'),
                'cta_label' => 'View booking',
                'title' => 'Your Appointment Is Coming Up',
                'subtitle' => 'A quick reminder from your salon team.',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'starts_at' => $this->booking->starts_at->toIso8601String(),
            'message' => "Reminder: booking {$this->booking->code} is coming up.",
        ];
    }
}
