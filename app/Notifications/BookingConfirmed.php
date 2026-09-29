<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Support\CalendarLinkGenerator;
use App\Support\CurrencyFormatter;
use App\Support\DurationFormatter;
use App\Support\GuestBookingLinkGenerator;
use App\Support\IcsGenerator;
use App\Support\NotificationPreferenceService;
use App\Support\SalonTimezone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brief §4 / Phase 7 item 8: branded confirmation email with an .ics attachment, queued (implements
 * ShouldQueue — retryable via the queue's own retry mechanism, not a custom one) and logged in
 * `notification_logs` by the global `LogNotificationDispatch` listener (not duplicated here).
 */
class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

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
        return 'Your appointment is confirmed. Booking ' . $this->booking->code . ' is on ' . $this->booking->starts_at->format('D, M j, Y') . ' at ' . $this->booking->starts_at->format('g:i A') . '. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
    }

    public function toWhatsapp(mixed $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'starts_at' => $this->booking->starts_at->toIso8601String(),
            'message' => "Your booking {$this->booking->code} is confirmed.",
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['items.service']);
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $customerName = $booking->customer?->name ?? $booking->guest_name ?? 'there';
        $localStart = $booking->starts_at->clone()->setTimezone(SalonTimezone::DISPLAY);

        return (new MailMessage)
            ->subject("Booking confirmed — {$booking->code}")
            ->view('mail.booking-confirmation', [
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'total_duration' => DurationFormatter::format((int) $booking->items->sum('duration_snapshot')),
                'total' => CurrencyFormatter::format($booking->total),
                'cta_url' => GuestBookingLinkGenerator::generate($booking),
                'cta_label' => 'Manage this booking',
                'google_calendar_url' => CalendarLinkGenerator::googleForBooking($booking),
                'ics_url' => CalendarLinkGenerator::icsForBooking($booking),
                'title' => 'Booking Confirmed',
                'subtitle' => 'Your appointment is locked in and ready to go.',
                'notes' => $booking->notes ?: null,
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ])
            ->attachData(IcsGenerator::forBooking($booking), 'appointment.ics', [
                'mime' => 'text/calendar',
            ]);
    }
}
