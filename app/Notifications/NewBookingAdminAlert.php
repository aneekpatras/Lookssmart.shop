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

/** Brief §4 / Phase 7 item 8: "admin notification" on every new booking. */
class NewBookingAdminAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        // The `ADMIN_NOTIFICATION_EMAIL` fallback routes on-demand (Notification::route('mail', ...))
        // to an AnonymousNotifiable, which has no `notifications()` relation — 'database' would fatal.
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
            'starts_at' => $this->booking->starts_at->toIso8601String(),
            'message' => "New booking {$this->booking->code}.",
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['items.service', 'customer']);
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $customerLabel = $booking->customer?->name ?? $booking->guest_name ?? 'Guest';
        $localStart = $booking->starts_at->clone()->setTimezone(SalonTimezone::DISPLAY);

        return (new MailMessage)
            ->subject("New booking — {$booking->code}")
            ->view('mail.admin-new-booking', [
                'customer_name' => $customerLabel,
                'service_name' => $serviceNames,
                'booking_code' => $booking->code,
                'booking_date' => $localStart->format('D, M j, Y'),
                'booking_time' => $localStart->format('g:i A'),
                'total_duration' => DurationFormatter::format((int) $booking->items->sum('duration_snapshot')),
                'total' => CurrencyFormatter::format($booking->total),
                'source' => $booking->source,
                'notes' => $booking->notes ?: null,
                'cta_url' => route('admin.bookings.show', $booking),
            ]);
    }
}
