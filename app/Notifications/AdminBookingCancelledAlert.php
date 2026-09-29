<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\CurrencyFormatter;
use App\Support\DurationFormatter;
use App\Support\SalonTimezone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin-facing counterpart to `NewBookingAdminAlert` — no equivalent existed for cancellations
 * before this (customers already got `BookingCancelled`, but admins were never told a booking had
 * been cancelled unless they happened to be looking at the list).
 */
class AdminBookingCancelledAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        // See NewBookingAdminAlert::via() — the ADMIN_NOTIFICATION_EMAIL fallback routes on-demand
        // to an AnonymousNotifiable, which has no `notifications()` relation.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return ['mail', 'database'];
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

    public function toMail(mixed $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['items.service', 'customer']);
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $customerLabel = $booking->customer?->name ?? $booking->guest_name ?? 'Guest';
        $localStart = $booking->starts_at->clone()->setTimezone(SalonTimezone::DISPLAY);

        return (new MailMessage)
            ->subject("Booking cancelled — {$booking->code}")
            ->view('mail.admin-booking-cancelled', [
                'customer_name' => $customerLabel,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'total_duration' => DurationFormatter::format((int) $booking->items->sum('duration_snapshot')),
                'total' => CurrencyFormatter::format($booking->total),
                'cancellation_reason' => $booking->cancellation_reason ?: null,
                'cta_url' => route('admin.bookings.show', $booking),
            ]);
    }
}
