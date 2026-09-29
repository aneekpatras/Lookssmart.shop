<?php

namespace App\Notifications;

use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Support\CurrencyFormatter;
use App\Support\DurationFormatter;
use App\Support\NotificationPreferenceService;
use App\Support\SalonTimezone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Brief §4 / Phase 8 item 2: "cancelled" branded email. */
class BookingCancelled extends Notification implements ShouldQueue
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
        return 'Your appointment ' . $this->booking->code . ' has been cancelled. We hope to see you again soon. Stop SMS: ' . NotificationPreferenceService::unsubscribeUrl($notifiable, 'sms');
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
            ->subject("Booking cancelled — {$booking->code}")
            ->view('mail.booking-cancelled', [
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'total_duration' => DurationFormatter::format((int) $booking->items->sum('duration_snapshot')),
                'total' => CurrencyFormatter::format($booking->total),
                'cancellation_reason' => $booking->cancellation_reason ?: null,
                'cta_url' => url('/book'),
                'cta_label' => 'Book again',
                'title' => 'Your Appointment Was Cancelled',
                'subtitle' => 'We’re sorry to see your booking go, but we’d love to welcome you back soon.',
                'unsubscribe_url' => NotificationPreferenceService::unsubscribeUrl($notifiable, 'mail'),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'message' => "Booking {$this->booking->code} was cancelled.",
        ];
    }
}
