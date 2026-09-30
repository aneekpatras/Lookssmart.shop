<?php

namespace App\Actions\Booking;

use App\Events\BookingRescheduled;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Setting;
use App\Models\Staff;
use App\Notifications\BookingRescheduled as BookingRescheduledNotification;
use App\Services\AvailabilityEngine;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brief §4 / Phase 7 item 7: "Reschedule ... flows with policy windows, reason capture, and slot
 * release." Reuses the exact same three-layer double-booking guard as `CreateBookingAction` (database-backed
 * hold → transaction + `lockForUpdate` → status-aware unique index) for the NEW slot — a reschedule
 * is really "release the old slot, book the new one" and must be exactly as safe as a fresh booking.
 */
class RescheduleBookingAction
{
    private const RESCHEDULABLE_STATUSES = ['pending', 'confirmed'];

    public function __construct(
        private readonly SlotHoldService $slotHoldService,
        private readonly AvailabilityEngine $availabilityEngine,
    ) {}

    public function execute(
        Booking $booking,
        int $staffId,
        CarbonImmutable $startsAt,
        string $timezone,
        ?string $reason,
        ?int $rescheduledBy,
        bool $bypassPolicyWindow = false,
    ): Booking {
        if (! in_array($booking->status, self::RESCHEDULABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'booking' => "A booking with status \"{$booking->status}\" can no longer be rescheduled.",
            ]);
        }

        if (! $bypassPolicyWindow && $booking->isWithinCancellationWindow()) {
            $windowHours = (int) (Setting::get('booking.cancellation_window_hours', 24) ?? 24);

            throw ValidationException::withMessages([
                'booking' => "This booking can no longer be rescheduled online — it starts in less than {$windowHours} hours. Please contact the salon directly.",
            ]);
        }

        $blockMinutes = $booking->items->sum('duration_snapshot')
            + $booking->items->map(fn ($item) => $item->service?->buffer_min ?? 0)->max();
        $endsAt = $startsAt->addMinutes((int) $blockMinutes);

        $isAvailable = $this->availabilityEngine->isStaffFreeAt(
            $staffId,
            $booking->items->pluck('service_id')->all(),
            $startsAt,
            $timezone,
        );

        if (! $isAvailable) {
            throw new SlotUnavailableException($this->alternatives($booking, $startsAt, $timezone));
        }

        $holdToken = $this->slotHoldService->hold($staffId, $startsAt);

        if (! $holdToken) {
            throw new SlotUnavailableException($this->alternatives($booking, $startsAt, $timezone));
        }

        $originalStart = $booking->starts_at;

        try {
            DB::transaction(function () use ($booking, $staffId, $startsAt, $endsAt, $reason, $rescheduledBy, $originalStart) {
                Staff::whereKey($staffId)->lockForUpdate()->first();

                $booking->staff_id = $staffId;
                $booking->starts_at = $startsAt;
                $booking->ends_at = $endsAt;
                $booking->save();

                BookingStatusLog::create([
                    'booking_id' => $booking->id,
                    'from_status' => $booking->status,
                    'to_status' => $booking->status,
                    'changed_by' => $rescheduledBy,
                    'reason' => $reason ?: "Rescheduled from {$originalStart->toIso8601String()} to {$startsAt->toIso8601String()}",
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            $this->slotHoldService->release($staffId, $startsAt, $holdToken);

            if ((string) $e->getCode() === '23000') {
                throw new SlotUnavailableException($this->alternatives($booking, $startsAt, $timezone));
            }

            throw $e;
        }

        $this->slotHoldService->release($staffId, $startsAt, $holdToken);

        $fresh = $booking->fresh();
        $fresh->customer?->notify(new BookingRescheduledNotification($fresh, $originalStart));
        event(new BookingRescheduled($fresh, $originalStart));

        return $fresh;
    }

    /**
     * Ad hoc task 30 made the slot list a salon-wide capacity grid (`$staffId` no longer applies to
     * it) — these are other generally-open times, not other times this one staff member is free.
     */
    private function alternatives(Booking $booking, CarbonImmutable $startsAt, string $timezone): Collection
    {
        $serviceIds = $booking->items->pluck('service_id')->all();

        return $this->availabilityEngine
            ->getSlots($serviceIds, $startsAt->toDateString(), $timezone)
            ->reject(fn (array $slot) => $slot['starts_at'] === $startsAt->toIso8601String())
            ->filter(fn (array $slot) => $slot['is_available'])
            ->take(3)
            ->values();
    }
}
