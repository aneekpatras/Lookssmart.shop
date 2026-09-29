<?php

namespace App\Actions\Booking;

use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminBookingCancelledAlert;
use App\Notifications\BookingCancelled as BookingCancelledNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Brief §4 / Phase 7 item 7: "Cancel flows with policy windows, reason capture, and slot release."
 * The slot itself is "released" by the state-aware unique index (see the migration this sub-step
 * added) — once `status` moves to `cancelled`, the generated `active_slot_key` column goes NULL,
 * so the exact same staff+time becomes bookable again immediately, not just conceptually free.
 */
class CancelBookingAction
{
    public function __construct(private readonly TransitionBookingStatusAction $transition) {}

    public function execute(Booking $booking, ?string $reason, ?int $cancelledBy, bool $bypassPolicyWindow = false): Booking
    {
        if (! $bypassPolicyWindow && $booking->isWithinCancellationWindow()) {
            $windowHours = (int) (Setting::get('booking.cancellation_window_hours', 24) ?? 24);

            throw ValidationException::withMessages([
                'booking' => "This booking can no longer be cancelled online — it starts in less than {$windowHours} hours. Please contact the salon directly.",
            ]);
        }

        $booking = $this->transition->execute($booking, 'cancelled', $reason, $cancelledBy);

        // See CreateBookingAction::sendNotifications() — under QUEUE_CONNECTION=sync, a ShouldQueue
        // notification's mail exception throws synchronously right here, so each dispatch is
        // individually try/caught: a notification failure must never turn a successful cancellation
        // into a 500 for the customer.
        try {
            $booking->customer?->notify(new BookingCancelledNotification($booking));
        } catch (\Throwable $e) {
            Log::error("Failed to send BookingCancelled for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        $admins = User::role(['admin', 'super-admin'])->get();

        try {
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new AdminBookingCancelledAlert($booking));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send AdminBookingCancelledAlert for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        try {
            $adminEmail = config('mail.admin_notification_email');
            if ($adminEmail && ! $admins->pluck('email')->contains($adminEmail)) {
                Notification::route('mail', $adminEmail)->notify(new AdminBookingCancelledAlert($booking));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send AdminBookingCancelledAlert to ADMIN_NOTIFICATION_EMAIL for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        event(new BookingCancelled($booking));

        return $booking;
    }
}
