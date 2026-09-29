<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Support\BookingStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for any booking status change — Brief §4/item 9's state machine guard plus
 * a `BookingStatusLog` row, in one place, so nothing can change a booking's status without both.
 */
class TransitionBookingStatusAction
{
    public function execute(Booking $booking, string $toStatus, ?string $reason = null, ?int $changedBy = null): Booking
    {
        BookingStateMachine::assertTransition($booking->status, $toStatus);

        return DB::transaction(function () use ($booking, $toStatus, $reason, $changedBy) {
            $fromStatus = $booking->status;

            $booking->status = $toStatus;
            if ($toStatus === 'cancelled' && $reason) {
                $booking->cancellation_reason = $reason;
            }
            $booking->save();

            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $booking;
        });
    }
}
