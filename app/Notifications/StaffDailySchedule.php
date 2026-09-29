<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Brief §4 / Phase 8 item 2: "staff daily schedule" — a fixed daily digest, not an admin-configurable
 * `ReminderRule` (there's no "event" in the rule-builder's enum for this), so both this notification
 * and its trigger (`app/Console/Commands/SendStaffDailySchedules.php`) are built in this same
 * sub-step rather than deferred to sub-step 2's rule engine.
 *
 * @param Collection<int, Booking> $bookings
 */
class StaffDailySchedule extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Collection $bookings) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return array_values(array_filter([
            'mail',
            'database',
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? SmsChannel::class : null,
            filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token')) ? WhatsappChannel::class : null,
        ]));
    }

    public function toSms(mixed $notifiable): string
    {
        return 'Today you have ' . $this->bookings->count() . ' appointment(s).';
    }

    public function toWhatsapp(mixed $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $staffName = $notifiable instanceof User ? $notifiable->name : 'there';
        $items = [];

        foreach ($this->bookings as $booking) {
            $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
            $customerName = $booking->customer?->name ?? $booking->guest_name ?? 'Guest';

            $items[] = [
                'time' => $booking->starts_at->format('g:i A'),
                'customer_name' => $customerName,
                'service_name' => $serviceNames,
            ];
        }

        return (new MailMessage)
            ->subject('Your schedule for today')
            ->view('mail.staff-schedule', [
                'staff_name' => $staffName,
                'booking_count' => $this->bookings->count(),
                'items' => $items,
                'cta_url' => url('/admin/schedule'),
                'cta_label' => 'Open schedule',
                'title' => 'Today’s Schedule',
                'subtitle' => 'Your upcoming appointments at a glance.',
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'booking_count' => $this->bookings->count(),
            'message' => "You have {$this->bookings->count()} appointment(s) today.",
        ];
    }
}
