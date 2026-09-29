<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\ReminderRule;
use App\Notifications\ReviewRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class RequestBookingReviews extends Command
{
    protected $signature = 'bookings:request-reviews {--now= : ISO-8601 time used for a deterministic run}';

    protected $description = 'Dispatch review requests for completed bookings without duplicates';

    public function handle(): int
    {
        $clock = $this->option('now')
            ? CarbonImmutable::parse($this->option('now'))
            : CarbonImmutable::now();
        $sent = 0;

        ReminderRule::active()
            ->where('event', 'review_request')
            ->where('direction', 'after')
            ->get()
            ->each(function (ReminderRule $rule) use ($clock, &$sent): void {
                $cutoff = $clock->subMinutes($rule->offset_minutes);

                Booking::query()
                    ->with('customer')
                    ->where('status', 'completed')
                    ->where('ends_at', '<=', $cutoff)
                    ->whereNotNull('customer_id')
                    ->each(function (Booking $booking) use ($rule, &$sent): void {
                        $customer = $booking->customer;
                        $key = "booking-review:{$booking->id}:{$rule->id}";

                        if (! $customer || ! $this->claim($key, $customer->email)) {
                            return;
                        }

                        $customer->notify(new ReviewRequest($booking, $rule->channels ?: ['email']));
                        $sent++;
                    });
            });

        $this->info("Dispatched {$sent} review request(s).");

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
