<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Actions\Booking\SlotUnavailableException;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\QuoteRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\AvailabilityEngine;
use App\Services\PriceQuoteService;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Public-facing booking endpoints (Brief §4 / Phase 7). JSON responses rather than Inertia pages —
 * Phase 9 (Public Website) builds the actual booking-page UI that will call these; this phase's job
 * is the engine and API surface, not the polished frontend.
 */
class BookingController extends Controller
{
    /**
     * Ad hoc task 30: the customer no longer picks a slot tied to a specific staff member (the public
     * slot list is a salon-wide capacity grid), so `staff_id` is now optional here. When omitted, a
     * real staff member is resolved and locked internally — invisibly — via `resolveStaffCandidates()`
     * ∘ `SlotHoldService::hold()`, trying each qualifying candidate in turn since a candidate found
     * free a moment ago can still lose the race for its own database-backed lock to a different concurrent
     * request. `staff_id` is still HONOURED when a caller does supply one (e.g. an internal/admin flow
     * choosing a specific staff member on purpose), unchanged from the pre-refactor behavior.
     *
     * A per-staff hold alone doesn't enforce `max_bookings_per_slot` — two customers auto-resolved
     * onto two DIFFERENT, individually-free staff would both sail straight past it. A numbered
     * capacity "seat" (`SlotHoldService::holdCapacitySeat()`) is claimed FIRST, atomically, budgeted
     * from `getSlots()`'s own `booked_count` for this exact slot, and only released again if every
     * staff candidate then fails — the response's `capacity_seat` must be echoed back on release()
     * (and ideally store(), see below) exactly like `hold_token`/`staff_id` already are.
     */
    public function hold(Request $request, AvailabilityEngine $availabilityEngine, SlotHoldService $slotHoldService): JsonResponse
    {
        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
        ]);
        $startsAt = CarbonImmutable::parse($data['starts_at'], $data['timezone'])->setTimezone('UTC');

        $slot = $availabilityEngine->getSlots($data['service_ids'], $startsAt->toDateString(), $data['timezone'])
            ->firstWhere('starts_at', $startsAt->toIso8601String());

        if (! $slot || ! $slot['is_available']) {
            return response()->json(['message' => 'That time is no longer available.'], 409);
        }

        $budget = (int) Setting::get('booking.max_bookings_per_slot', 3) - $slot['booked_count'];
        $capacitySeat = $slotHoldService->holdCapacitySeat($startsAt, $budget);

        if (! $capacitySeat) {
            return response()->json(['message' => 'That time is being held by another guest.'], 409);
        }

        if ($data['staff_id'] ?? null) {
            $available = $availabilityEngine->isStaffFreeAt($data['staff_id'], $data['service_ids'], $startsAt, $data['timezone']);
            $candidates = $available ? collect([$data['staff_id']]) : collect();
        } else {
            $candidates = $availabilityEngine->resolveStaffCandidates($data['service_ids'], $startsAt, $data['timezone']);
        }

        // Ad hoc task 41: booking no longer requires a single staff member to cover every selected
        // service — the salon-wide capacity seat claimed above is the real, authoritative guard
        // against overbooking this slot regardless of staff. A real covering staff member is still
        // preferred and used when one exists (accurate schedules/commission); `null` is a genuine,
        // final fallback (not a placeholder id), same as an explicit `staff_id` request never resolves
        // one either.
        if ($candidates->isEmpty() && ! ($data['staff_id'] ?? null)) {
            $candidates = collect([null]);
        }

        foreach ($candidates as $staffId) {
            $token = $slotHoldService->hold($staffId, $startsAt);

            if ($token) {
                return response()->json([
                    'hold_token' => $token,
                    'staff_id' => $staffId,
                    'capacity_seat' => $capacitySeat,
                    'expires_in' => (int) Setting::get('booking.hold_minutes', 5) * 60,
                ], 201);
            }
        }

        $slotHoldService->releaseCapacitySeat($startsAt, $capacitySeat);

        return response()->json(['message' => 'That time is being held by another guest.'], 409);
    }

    public function release(Request $request, SlotHoldService $slotHoldService): JsonResponse
    {
        $data = $request->validate([
            // Nullable — ad hoc task 41's null-staff hold (SlotHoldService::release() already
            // no-ops for it, but a `required` rule here would 422 before that code ever runs).
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'hold_token' => ['required', 'string', 'uuid'],
            'capacity_seat' => ['nullable', 'string'],
        ]);
        $startsAt = CarbonImmutable::parse($data['starts_at'])->setTimezone('UTC');
        $slotHoldService->release($data['staff_id'] ?? null, $startsAt, $data['hold_token']);

        if (! empty($data['capacity_seat'])) {
            $slotHoldService->releaseCapacitySeat($startsAt, $data['capacity_seat']);
        }

        return response()->json(['released' => true]);
    }

    public function availability(Request $request, AvailabilityEngine $availabilityEngine): JsonResponse
    {
        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'date' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $slots = $availabilityEngine->getSlots(
            $data['service_ids'],
            $data['date'],
            $data['timezone'] ?? null,
        );

        return response()->json(['slots' => $slots]);
    }

    public function quote(QuoteRequest $request, PriceQuoteService $priceQuoteService): JsonResponse
    {
        $data = $request->validated();

        try {
            $quote = $priceQuoteService->quote($data['service_ids'], $data['code'] ?? null, $request->user()?->id);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json($quote);
    }

    /**
     * Ad hoc task 30: `staff_id` is optional here too. When the client supplies one (the exact
     * pre-refactor behavior — still exercised directly by `CreateBookingActionTest`/
     * `BookingConcurrencyTest`), it's honoured as-is, unaffected by the capacity guard below other
     * than sharing its overhead. When omitted (the current public wizard, which no longer knows a
     * specific staff member), every currently-qualifying candidate is tried in turn against the real
     * 3-layer double-booking guard in `CreateBookingAction` until one succeeds — a
     * `SlotUnavailableException` from one candidate just means try the next, not that the whole
     * request fails; only exhausting every candidate is a genuine "no longer available."
     *
     * Claims its OWN capacity seat first (not just whatever the wizard's earlier `hold()` claimed) —
     * `store()` must independently enforce `max_bookings_per_slot` even when called with no prior
     * hold at all (exactly how `CreateBookingActionTest`/`BookingConcurrencyTest` call it directly),
     * since the per-staff guard alone would otherwise let 2 customers auto-resolved onto 2 different,
     * individually-free staff both sail past the cap.
     */
    public function store(StoreBookingRequest $request, CreateBookingAction $action, AvailabilityEngine $availabilityEngine, SlotHoldService $slotHoldService): JsonResponse
    {
        $data = $request->validated();

        // `bookings` has no dedicated `subject` column — folded onto `notes` here, the same
        // precedent `submitContact()` already uses for `leads.notes` (also columnless for it).
        $notes = trim(
            (! empty($data['subject']) ? "Subject: {$data['subject']}\n\n" : '') . ($data['notes'] ?? ''),
        );
        $startsAt = CarbonImmutable::parse($data['starts_at'], $data['timezone'])->setTimezone('UTC');

        $slot = $availabilityEngine->getSlots($data['service_ids'], $startsAt->toDateString(), $data['timezone'])
            ->firstWhere('starts_at', $startsAt->toIso8601String());

        if (! $slot || ! $slot['is_available']) {
            return response()->json(['message' => 'That time is no longer available.'], 409);
        }

        $budget = (int) Setting::get('booking.max_bookings_per_slot', 3) - $slot['booked_count'];
        $capacitySeat = $slotHoldService->holdCapacitySeat($startsAt, $budget);

        if (! $capacitySeat) {
            return response()->json(['message' => 'That time is no longer available.'], 409);
        }

        $candidates = ($data['staff_id'] ?? null)
            ? collect([$data['staff_id']])
            : $availabilityEngine->resolveStaffCandidates($data['service_ids'], $startsAt, $data['timezone']);

        // Ad hoc task 41: see `hold()`'s matching comment — fall back to a staff-less booking rather
        // than blocking checkout, but only when no SPECIFIC staff_id was requested in the first place.
        if ($candidates->isEmpty() && ! ($data['staff_id'] ?? null)) {
            $candidates = collect([null]);
        }

        $lastFailure = null;

        foreach ($candidates as $staffId) {
            try {
                $booking = $action->execute(
                    serviceIds: $data['service_ids'],
                    staffId: $staffId,
                    startsAt: $startsAt,
                    quote: $data['quote'],
                    timezone: $data['timezone'],
                    source: 'website',
                    customerId: $request->user()?->id,
                    guestName: $data['guest_name'] ?? null,
                    guestEmail: $data['guest_email'] ?? null,
                    guestPhone: $data['guest_phone'] ?? null,
                    notes: $notes !== '' ? $notes : null,
                );

                // The real DB row now permanently accounts for this seat in every future getSlots()
                // count — the ephemeral database-backed claim has done its job of blocking concurrent checkouts
                // during this request and can be released immediately rather than waiting out its TTL.
                $slotHoldService->releaseCapacitySeat($startsAt, $capacitySeat);

                return response()->json([
                    'code' => $booking->code,
                    'starts_at' => $booking->starts_at->toIso8601String(),
                    'ends_at' => $booking->ends_at->toIso8601String(),
                    'total' => $booking->total,
                    'status' => $booking->status,
                ], 201);
            } catch (SlotUnavailableException $e) {
                $lastFailure = $e;

                continue;
            } catch (ValidationException $e) {
                $slotHoldService->releaseCapacitySeat($startsAt, $capacitySeat);

                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
        }

        $slotHoldService->releaseCapacitySeat($startsAt, $capacitySeat);

        return response()->json([
            'message' => $lastFailure?->getMessage() ?? 'That time is no longer available.',
            'alternatives' => $lastFailure?->alternatives() ?? collect(),
        ], 409);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBookingAction $action): JsonResponse
    {
        try {
            $action->execute(
                booking: $booking,
                reason: $request->validated('reason'),
                cancelledBy: $request->user()->id,
                bypassPolicyWindow: $request->user()->can('bookings.manage'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['code' => $booking->code, 'status' => $booking->fresh()->status]);
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking, RescheduleBookingAction $action): JsonResponse
    {
        $data = $request->validated();

        try {
            $rescheduled = $action->execute(
                booking: $booking,
                staffId: $data['staff_id'],
                startsAt: CarbonImmutable::parse($data['starts_at'], $data['timezone'])->setTimezone('UTC'),
                timezone: $data['timezone'],
                reason: $data['reason'] ?? null,
                rescheduledBy: $request->user()->id,
                bypassPolicyWindow: $request->user()->can('bookings.manage'),
            );
        } catch (SlotUnavailableException $e) {
            return response()->json(['message' => $e->getMessage(), 'alternatives' => $e->alternatives()], 409);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'code' => $rescheduled->code,
            'starts_at' => $rescheduled->starts_at->toIso8601String(),
            'ends_at' => $rescheduled->ends_at->toIso8601String(),
            'status' => $rescheduled->status,
        ]);
    }
}
