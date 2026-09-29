<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\ReminderRule;
use App\Notifications\BookingReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--now= : ISO-8601 time used for a deterministic run}';

    protected $description = 'Dispatch due booking reminders without duplicates';

    public function handle(): int
    {
        $clock = $this->option('now')
            ? CarbonImmutable::parse($this->option('now'))
            : CarbonImmutable::now();
        $sent = 0;

        ReminderRule::active()
            ->where('event', 'before_booking')
            ->where('direction', 'before')
            ->whereIn('offset_minutes', [120, 1440])
            ->get()
            ->each(function (ReminderRule $rule) use ($clock, &$sent): void {
                $windowStart = $clock->addMinutes($rule->offset_minutes);
                $windowEnd = $windowStart->addMinutes(5);

                Booking::query()
                    ->with('customer')
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->whereBetween('starts_at', [$windowStart, $windowEnd])
                    ->whereNotNull('customer_id')
                    ->each(function (Booking $booking) use ($rule, &$sent): void {
                        $customer = $booking->customer;
                        $key = "booking-reminder:{$booking->id}:{$rule->id}";

                        if (! $customer || ! $this->claim($key, $customer->email)) {
                            return;
                        }

                        $customer->notify(new BookingReminder($booking, $rule->channels ?: ['email']));
                        $sent++;
                    });
            });

        $this->info("Dispatched {$sent} booking reminder(s).");

        return self::SUCCESS;
    }

    private function claim(string $key, string $recipient): bool
    {
        try {
            NotificationLog::create([
                'channel' => 'reminder',
                'recipient' => $recipient,
                'status' => 'pending',
                'reminder_key' => $key,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
