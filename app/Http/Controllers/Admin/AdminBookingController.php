<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\TransitionBookingStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Payment;
use App\Support\CurrencyFormatter;
use App\Support\InvalidBookingTransitionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 14 sub-step 2: replaces the `Admin/Bookings` and `Admin/Availability` placeholder shells
 * with a real read/manage view onto the Phase 7 booking engine. `BookingPolicy::viewAny()` was
 * tightened alongside this (it previously also passed for `bookings.view_own`, which must only
 * unlock a scoped view, never this full admin-wide list).
 */
class AdminBookingController extends Controller
{
    private const STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'];

    /**
     * Ad hoc task 37: `created_at` (when the booking was actually placed), not `starts_at` (the
     * appointment's own future/past date), is first — a booking made moments ago for next month
     * must appear above one made last week for tomorrow, so staff can see incoming activity as it
     * happens instead of it being buried under whatever else has a sooner appointment time.
     */
    private const SORTABLE_COLUMNS = ['created_at', 'starts_at', 'total', 'status'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Booking::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'created_at';

        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        $bookings = Booking::query()
            ->with(['items.service', 'customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('starts_at', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => $bookings->through(fn (Booking $booking) => $this->bookingRow($booking)),
            'statuses' => self::STATUSES,
            'filters' => [
                'status' => $request->string('status')->value() ?: null,
                'from' => $request->string('from')->value() ?: null,
                'to' => $request->string('to')->value() ?: null,
                'search' => $request->string('search')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function show(Booking $booking): Response
    {
        $this->authorize('view', $booking);

        $booking->load(['items.service', 'staff.user', 'customer', 'payments', 'statusLogs' => fn ($query) => $query->with('changedBy:id,name')->latest('created_at')]);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => [
                ...$this->bookingRow($booking),
                'staff_id' => $booking->staff_id,
                'staff_name' => $booking->staff?->user?->name,
                'notes' => $booking->notes,
                'cancellation_reason' => $booking->cancellation_reason,
                'guest_email' => $booking->guest_email,
                'guest_phone' => $booking->guest_phone,
                'discount' => (string) $booking->discount,
                'tax' => (string) $booking->tax,
                'items' => $booking->items->map(fn ($item) => [
                    'service' => $item->service?->name ?? 'Service',
                    'price' => CurrencyFormatter::format($item->price_snapshot ?? 0),
                ]),
                'payments' => $booking->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => CurrencyFormatter::format($payment->amount),
                    'status' => $payment->status,
                ]),
                'status_log' => $booking->statusLogs->map(fn (BookingStatusLog $log) => [
                    'from' => $log->from_status,
                    'to' => $log->to_status,
                    'reason' => $log->reason,
                    'changed_by' => $log->changedBy?->name ?? 'System',
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function updateStatus(
        Request $request,
        Booking $booking,
        TransitionBookingStatusAction $transition,
        CancelBookingAction $cancel,
    ): RedirectResponse {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($data['status'] === 'cancelled') {
                // An admin acting directly on a booking is exercising staff discretion, not the
                // customer-facing self-service flow CancelBookingAction's policy window protects —
                // bypassed here the same way an admin override is elsewhere in this app.
                $cancel->execute($booking, $data['reason'] ?? null, $request->user()->id, bypassPolicyWindow: true);
            } else {
                $transition->execute($booking, $data['status'], $data['reason'] ?? null, $request->user()->id);
            }
        } catch (InvalidBookingTransitionException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('success', "Booking marked \"{$data['status']}\".");
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        // Soft-delete only (Booking uses SoftDeletes) — the record, its items, and payment history
        // stay intact for reporting/audit; this only removes it from the active admin list.
        $booking->delete();

        return back()->with('success', "Booking \"{$booking->code}\" deleted.");
    }

    /** @return array<string, mixed> */
    private function bookingRow(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'code' => $booking->code,
            'status' => $booking->status,
            'starts_at' => $booking->starts_at?->toIso8601String(),
            'ends_at' => $booking->ends_at?->toIso8601String(),
            'customer_name' => $booking->customer?->name ?? $booking->guest_name,
            'services' => $booking->items->map(fn ($item) => $item->service?->name)->filter()->values(),
            'duration_minutes' => $booking->items->sum('duration_snapshot'),
            'total' => CurrencyFormatter::format($booking->total),
            'source' => $booking->source,
        ];
    }
}
