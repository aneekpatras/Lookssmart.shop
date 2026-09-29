<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Actions\Booking\SlotUnavailableException;
use App\Http\Requests\Booking\GuestCancelBookingRequest;
use App\Http\Requests\Booking\GuestRescheduleBookingRequest;
use App\Models\Booking;
use App\Support\GuestBookingLinkGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Decision #21 (Phase 3, implemented here in Phase 7): passwordless guest booking management via a
 * signed, single-booking link — emailed on confirmation (see BookingConfirmed's magic-link line).
 * Every method here re-validates the signature on every request via the `signed` route middleware
 * (never trusted once and cached) and NEVER calls `Auth::login()` or touches the session — this is a
 * scoped capability URL for exactly one resource, not an authentication mechanism, which is what
 * makes "replay to escalate" structurally impossible: there is no account session to pivot from.
 */
class BookingManageController extends Controller
{
    public function show(Booking $booking): JsonResponse
    {
        $this->assertLinkStillValid($booking);

        $booking->loadMissing(['items.service', 'staff.user']);

        return response()->json([
            'code' => $booking->code,
            'status' => $booking->status,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'staff' => $booking->staff?->user?->name,
            'services' => $booking->items->map(fn ($item) => $item->service?->name)->filter()->values(),
            'total' => $booking->total,
            // A signature is only valid for the exact route it was generated for — the "show" link a
            // guest opens from email can't be reused as-is for a POST to a different route, so this
            // response hands back freshly-signed URLs for the actions this page can actually offer.
            'cancel_url' => GuestBookingLinkGenerator::generate($booking, 'booking.manage.cancel'),
            'reschedule_url' => GuestBookingLinkGenerator::generate($booking, 'booking.manage.reschedule'),
        ]);
    }

    public function cancel(GuestCancelBookingRequest $request, Booking $booking, CancelBookingAction $action): JsonResponse
    {
        $this->assertLinkStillValid($booking);

        try {
            $action->execute($booking, $request->validated('reason'), Auth::id(), bypassPolicyWindow: false);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['code' => $booking->code, 'status' => $booking->fresh()->status]);
    }

    public function reschedule(GuestRescheduleBookingRequest $request, Booking $booking, RescheduleBookingAction $action): JsonResponse
    {
        $this->assertLinkStillValid($booking);
        $data = $request->validated();

        try {
            $rescheduled = $action->execute(
                booking: $booking,
                staffId: $data['staff_id'],
                startsAt: CarbonImmutable::parse($data['starts_at'], $data['timezone'])->setTimezone('UTC'),
                timezone: $data['timezone'],
                reason: $data['reason'] ?? null,
                rescheduledBy: Auth::id(),
                bypassPolicyWindow: false,
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

    /**
     * Brief/Decision #21: "invalidated once cancelled or completed" — a signed URL only proves the
     * link hasn't been tampered with, it knows nothing about mutable app state. This is the check
     * that actually enforces the runtime half of that requirement, independent of the signature.
     */
    private function assertLinkStillValid(Booking $booking): void
    {
        abort_if(
            in_array($booking->status, ['cancelled', 'completed', 'no_show'], true),
            410,
            'This booking can no longer be managed online.',
        );
    }
}
