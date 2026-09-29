<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Support\NotificationPreferenceService;
use App\Support\SalonTimezone;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Brief §4 / Phase 8 item 2: "rescheduled" branded email — sent when an existing booking moves time/staff. */
class BookingRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly CarbonInterface $previousStartsAt,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return array_values(array_filter([
            MailChannel::class,
            'database',
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? SmsChannel::class : null,
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? WhatsappChannel::class : null,
        ]));
    }

    public function toSms(mixed $notifiable): string
    {
        return 'Your appointment ' . $this->booking->code . ' was rescheduled to ' . $this->booking->starts_at->format('D, M j, Y') . ' at ' . $this->booking->starts_at->format('g:i A') . '. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
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
        $localPreviousStart = $this->previousStartsAt->clone()->setTimezone(SalonTimezone::DISPLAY);
        $localStart = $booking->starts_at->clone()->setTimezone(SalonTimezone::DISPLAY);

        return (new MailMessage)
            ->subject("Booking rescheduled — {$booking->code}")
            ->view('mail.booking-rescheduled', [
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'previous_booking_date' => $localPreviousStart->format('D, M j, Y'),
                'previous_booking_time' => $localPreviousStart->format('g:i A'),
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'cta_url' => url('/book'),
                'cta_label' => 'Review booking',
                'title' => 'Your Appointment Was Rescheduled',
                'subtitle' => 'We’ve updated your appointment to a new time.',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'previous_starts_at' => $this->previousStartsAt->toIso8601String(),
            'starts_at' => $this->booking->starts_at->toIso8601String(),
            'message' => "Booking {$this->booking->code} was rescheduled.",
        ];
    }
}
