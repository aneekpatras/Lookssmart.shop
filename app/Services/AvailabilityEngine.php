<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\SalonHoliday;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Ad hoc task 30 refactor: customer-facing slot generation is now a fixed, salon-wide capacity grid,
 * completely detached from individual staff schedules — `getSlots()` no longer enumerates staff at
 * all. Each fixed-width slot (`booking.slot_minutes`, default 30 as of ad hoc task 35 — i.e. "fixed
 * 30-minute slots") across the salon's open hours is either open or full, based purely on how many
 * active/confirmed bookings of ANY staff already overlap it versus `booking.max_bookings_per_slot`.
 * Full slots are still returned (`is_available: false`), never hidden, so the picker can show them as
 * disabled. Ad hoc task 35 also removed all service-duration/closing-time slot validation outright —
 * every fixed grid mark up to and including closing time is offered regardless of how long the
 * selected services take; capacity is the only thing that can still disable a slot.
 *
 * A real appointment still needs a REAL staff member assigned for actual service delivery and for
 * this app's existing double-booking guard (`SlotHoldService`'s `slot_holds` row lock and
 * `CreateBookingAction`'s DB unique index are both keyed by `staff_id` — Brief §4 / Phase 7 item 2/3, unchanged by this
 * refactor). That per-staff resolution is now an internal, invisible concern handled by
 * `isStaffFreeAt()`/`resolveStaffCandidates()` below, called by `BookingController::hold()`/`store()`
 * AFTER the customer has picked a capacity slot — never exposed in the public slot list itself.
 */
class AvailabilityEngine
{
    public function __construct(private readonly SlotHoldService $slotHoldService) {}

    /**
     * The customer-facing capacity grid for one day. Fixed-width slots from salon open to close,
     * every slot returned (not just open ones) with an `is_available` flag reflecting the configured
     * per-slot booking cap — no staff lookup anywhere in this method.
     *
     * @param int[] $serviceIds
     * @return Collection<int, array{starts_at: string, ends_at: string, is_available: bool, booked_count: int}>
     */
    public function getSlots(array $serviceIds, string $date, ?string $timezone = null): Collection
    {
        $timezone = $timezone ?: (Setting::get('business.timezone') ?: 'UTC');
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();

        $services = Service::whereIn('id', $serviceIds)->get();

        if ($services->count() !== count($serviceIds) || $services->isEmpty()) {
            return collect();
        }

        $maxAdvanceDays = (int) Setting::get('booking.max_advance_days', 60);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($day->lt($today) || $day->gt($today->addDays($maxAdvanceDays))) {
            return collect();
        }

        if ($this->isHoliday($day)) {
            return collect();
        }

        $businessHour = BusinessHour::where('weekday', $day->dayOfWeek)->first();
        if (! $businessHour || $businessHour->is_closed || ! $businessHour->open_time || ! $businessHour->close_time) {
            return collect();
        }

        $windowStart = $day->setTimeFromTimeString($businessHour->open_time);
        // A '00:00:00' close time means "midnight at the end of the day", not the start of it — must
        // be resolved BEFORE any open>=close comparison, since comparing the raw strings would wrongly
        // treat a midnight close as "already closed" ('0' sorts before '2' lexicographically).
        $windowEnd = $businessHour->close_time === '00:00:00'
            ? $day->addDay()->startOfDay()
            : $day->setTimeFromTimeString($businessHour->close_time);

        if ($windowStart->gte($windowEnd)) {
            return collect();
        }

        $slotMinutes = max(1, (int) Setting::get('booking.slot_minutes', 30));
        $minLeadMinutes = (int) Setting::get('booking.min_lead_minutes', 60);
        $earliestAllowed = CarbonImmutable::now($timezone)->addMinutes($minLeadMinutes);
        $maxPerSlot = max(1, (int) Setting::get('booking.max_bookings_per_slot', 3));

        $windowStartUtc = $windowStart->setTimezone('UTC');
        $windowEndUtc = $windowEnd->setTimezone('UTC');

        // Ad hoc task 35: closing-time/service-duration slot validation removed outright, per an
        // explicit follow-up spec reversing task 34's own "disabled past closing" rule — every fixed
        // grid mark from open to close (INCLUSIVE of close itself, e.g. 9:00 PM is a real, selectable
        // mark on a 10 AM-9 PM day, not just the boundary a slot's own width must fit before) is
        // offered regardless of how long the selected services take; only the salon-wide capacity cap
        // below can still disable one. The active-bookings fetch window is widened by one slot width
        // past closing so a booking overlapping that final, intentionally-overflowing mark is still
        // correctly counted.
        $activeBookings = Booking::whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $windowEndUtc->addMinutes($slotMinutes))
            ->where('ends_at', '>', $windowStartUtc)
            ->get(['starts_at', 'ends_at']);

        $slots = collect();
        $cursor = $windowStart;

        while ($cursor->lte($windowEnd)) {
            $slotStart = $cursor;
            $slotEnd = $cursor->addMinutes($slotMinutes);
            $cursor = $slotEnd;

            // A lead-time violation (a slot in the immediate past/too-soon-to-book) is still excluded
            // outright, not shown disabled — genuinely stale grid marks (e.g. earlier today) aren't a
            // meaningful "this salon slot exists but is full" fact for the customer to see at all.
            if ($slotStart->lt($earliestAllowed)) {
                continue;
            }

            $slotStartUtc = $slotStart->setTimezone('UTC');
            $slotEndUtc = $slotEnd->setTimezone('UTC');

            $bookedCount = $activeBookings->filter(
                fn (Booking $booking) => $slotStartUtc->lt($booking->ends_at) && $slotEndUtc->gt($booking->starts_at),
            )->count();

            $slots->push([
                'starts_at' => $slotStartUtc->toIso8601String(),
                'ends_at' => $slotEndUtc->toIso8601String(),
                'is_available' => $bookedCount < $maxPerSlot,
                // Real, committed DB bookings only — deliberately excludes in-flight database-backed capacity
                // holds (`SlotHoldService::holdCapacitySeat()`), which `BookingController` checks
                // separately at hold()/store() time. `max_bookings_per_slot - booked_count` is exactly
                // the atomic-claim budget those two callers pass in.
                'booked_count' => $bookedCount,
            ]);
        }

        return $slots;
    }

    /**
     * Internal only — never exposed via the public slot list. Used once a customer has picked a
     * capacity slot, to find every currently-qualifying, genuinely-free staff member to actually
     * perform it (real working hours ∩ business hours, no time-off/booking conflict, not already
     * database-held). Ordered by id purely for determinism; the caller tries candidates in turn since a
     * candidate can lose a race for its own database-backed hold between here and the atomic lock attempt.
     *
     * @param int[] $serviceIds
     * @return Collection<int, int> staff ids
     */
    public function resolveStaffCandidates(array $serviceIds, CarbonImmutable $startsAt, ?string $timezone = null): Collection
    {
        $timezone = $timezone ?: (Setting::get('business.timezone') ?: 'UTC');

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->count() !== count($serviceIds) || $services->isEmpty()) {
            return collect();
        }

        $staffMembers = Staff::active()
            ->with(['workingHours', 'services:id'])
            ->get()
            ->filter(fn (Staff $staff) => collect($serviceIds)->every(
                fn (int $serviceId) => $staff->services->contains('id', $serviceId),
            ));

        return $staffMembers
            ->filter(fn (Staff $staff) => $this->staffCanTake($staff, $services, $startsAt, $timezone))
            ->pluck('id')
            ->sort()
            ->values();
    }

    /** Same check as `resolveStaffCandidates()`, for exactly one already-known staff member (the
     * explicit-staff hold/reschedule path, kept for backward compatibility with callers that still
     * want a specific staff member rather than an auto-assigned one). */
    public function isStaffFreeAt(int $staffId, array $serviceIds, CarbonImmutable $startsAt, ?string $timezone = null): bool
    {
        $timezone = $timezone ?: (Setting::get('business.timezone') ?: 'UTC');

        $staff = Staff::active()->with(['workingHours', 'services:id'])->find($staffId);
        if (! $staff || ! collect($serviceIds)->every(fn (int $id) => $staff->services->contains('id', $id))) {
            return false;
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->count() !== count($serviceIds) || $services->isEmpty()) {
            return false;
        }

        return $this->staffCanTake($staff, $services, $startsAt, $timezone);
    }

    private function staffCanTake(Staff $staff, Collection $services, CarbonImmutable $startsAt, string $timezone): bool
    {
        $day = $startsAt->setTimezone($timezone)->startOfDay();
        $weekday = $day->dayOfWeek;

        $maxAdvanceDays = (int) Setting::get('booking.max_advance_days', 60);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        if ($day->lt($today) || $day->gt($today->addDays($maxAdvanceDays)) || $this->isHoliday($day)) {
            return false;
        }

        $businessHour = BusinessHour::where('weekday', $weekday)->first();
        if (! $businessHour || $businessHour->is_closed || ! $businessHour->open_time || ! $businessHour->close_time) {
            return false;
        }

        $workingHour = $staff->workingHours->firstWhere('weekday', $weekday);
        if (! $workingHour) {
            return false;
        }

        $openTime = max($businessHour->open_time, $workingHour->start_time);
        $closeTime = min($businessHour->close_time, $workingHour->end_time);
        $windowStart = $day->setTimeFromTimeString($openTime);
        $windowEnd = $closeTime === '00:00:00'
            ? $day->addDay()->startOfDay()
            : $day->setTimeFromTimeString($closeTime);

        if ($windowStart->gte($windowEnd)) {
            return false;
        }

        $blockMinutes = (int) $services->sum('duration_min') + (int) $services->max('buffer_min');
        $slotStart = $startsAt->setTimezone($timezone);
        $slotEnd = $slotStart->addMinutes($blockMinutes);

        if ($slotStart->lt($windowStart) || $slotEnd->gt($windowEnd)) {
            return false;
        }

        $minLeadMinutes = (int) Setting::get('booking.min_lead_minutes', 60);
        if ($slotStart->lt(CarbonImmutable::now($timezone)->addMinutes($minLeadMinutes))) {
            return false;
        }

        $slotStartUtc = $slotStart->setTimezone('UTC');
        $slotEndUtc = $slotEnd->setTimezone('UTC');

        $hasTimeOffConflict = StaffTimeOff::where('staff_id', $staff->id)
            ->where('starts_at', '<', $slotEndUtc)
            ->where('ends_at', '>', $slotStartUtc)
            ->exists();
        if ($hasTimeOffConflict) {
            return false;
        }

        $hasBookingConflict = Booking::where('staff_id', $staff->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $slotEndUtc)
            ->where('ends_at', '>', $slotStartUtc)
            ->exists();
        if ($hasBookingConflict) {
            return false;
        }

        return ! $this->slotHoldService->isHeld($staff->id, $slotStartUtc);
    }

    private function isHoliday(CarbonImmutable $day): bool
    {
        return SalonHoliday::query()
            ->get(['starts_at', 'ends_at', 'is_recurring_yearly'])
            ->contains(function (SalonHoliday $holiday) use ($day) {
                if ($holiday->is_recurring_yearly) {
                    $start = $holiday->starts_at->setYear($day->year);
                    $end = $holiday->ends_at->setYear($day->year);
                } else {
                    $start = $holiday->starts_at;
                    $end = $holiday->ends_at;
                }

                return $day->toDateString() >= $start->toDateString() && $day->toDateString() <= $end->toDateString();
            });
    }
}
