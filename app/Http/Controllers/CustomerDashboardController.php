<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\UpdateCustomerPreferencesRequest;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $bookings = Booking::query()
            ->where('customer_id', $user->id)
            ->with(['items.service'])
            ->latest('starts_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Customer/Profile', [
            'bookings' => $bookings->through(fn (Booking $booking) => $this->bookingData($booking)),
            'upcoming' => Booking::query()
                ->where('customer_id', $user->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('starts_at', '>=', now())
                ->with(['items.service'])
                ->orderBy('starts_at')
                ->limit(3)
                ->get()
                ->map(fn (Booking $booking) => $this->bookingData($booking)),
            'profile' => [
                'phone' => $user->phone,
                'marketing_opt_in' => (bool) $user->customerProfile?->marketing_opt_in,
                'email_opt_out' => (bool) $user->customerProfile?->email_opt_out,
                'sms_opt_out' => (bool) $user->customerProfile?->sms_opt_out,
                'whatsapp_opt_out' => (bool) $user->customerProfile?->whatsapp_opt_out,
            ],
        ]);
    }

    /**
     * The dedicated "My Bookings" page (Brief follow-up task): every current and past appointment,
     * newest-first, with the fields the task names explicitly — booking id (the real `code`, not the
     * numeric PK, since `code` is what a customer would ever quote back to the salon), services,
     * date/time, total, and status — plus `can_cancel_free` so the frontend's fee-warning modal can
     * never disagree with what `CancelBookingAction` will actually accept.
     */
    public function bookings(Request $request): Response
    {
        $user = $request->user();
        $bookings = Booking::query()
            ->where('customer_id', $user->id)
            ->with(['items.service'])
            ->latest('starts_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customer/Bookings', [
            'bookings' => $bookings->through(fn (Booking $booking) => $this->bookingData($booking)),
            'cancellationWindowHours' => (int) (Setting::get('booking.cancellation_window_hours', 24) ?? 24),
        ]);
    }

    public function updatePreferences(UpdateCustomerPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $user->forceFill(['phone' => $data['phone'] ?? null])->save();
        $profile = $user->customerProfile()->firstOrCreate([]);
        $profile->forceFill([
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
            'email_opt_out' => (bool) ($data['email_opt_out'] ?? false),
            'sms_opt_out' => (bool) ($data['sms_opt_out'] ?? false),
            'whatsapp_opt_out' => (bool) ($data['whatsapp_opt_out'] ?? false),
        ])->save();

        return back()->with('status', 'Communication preferences updated.');
    }

    /** @return array<string, mixed> */
    private function bookingData(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'code' => $booking->code,
            'status' => $booking->status,
            'starts_at' => $booking->starts_at?->toIso8601String(),
            'ends_at' => $booking->ends_at?->toIso8601String(),
            'staff_id' => $booking->staff_id,
            'services' => $booking->items->map(fn ($item) => $item->service?->name)->filter()->values(),
            'total' => (string) $booking->total,
            // Real, not a client-side guess — `Booking::isCustomerCancellable()`/
            // `isWithinCancellationWindow()` are the exact same checks `CancelBookingAction` itself
            // applies, so the frontend's cancel button and fee-warning modal can never disagree with
            // what the API will actually accept.
            'can_cancel' => $booking->isCustomerCancellable(),
            'within_cancellation_fee_window' => $booking->isWithinCancellationWindow(),
        ];
    }
}
