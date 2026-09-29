<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerNotesRequest;
use App\Models\CustomerProfile;
use App\Models\DealRedemption;
use App\Models\Lead;
use App\Models\Message;
use App\Models\NotificationLog;
use App\Models\Review;
use App\Models\User;
use App\Support\CurrencyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 11 sub-step 4: a consolidated "Customer 360" view. `customer_profiles.total_spent`/`visits`
 * (Phase 2) are never written to anywhere in the app — dead denormalized columns, not a live source
 * of truth — so LTV/visit counts here are computed fresh from real `bookings` rows every time, not
 * trusted from those stale columns. `Lead`/`Message` have no FK to `users` (both are pre-account
 * capture forms), so their "history" here is matched by email as a best-effort heuristic, not a real
 * join — documented inline, not silently presented as exact.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerProfile::class);

        $customers = User::query()
            ->whereHas('customerProfile')
            ->with('customerProfile')
            ->withSum(['bookings as ltv' => fn ($query) => $query->where('status', 'completed')], 'total')
            ->withCount(['bookings as completed_visits' => fn ($query) => $query->where('status', 'completed')])
            ->withCount(['bookings as no_show_count' => fn ($query) => $query->where('status', 'no_show')])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = $request->string('search')->value();
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($request->filled('tag'), fn ($query) => $query->whereHas(
                'customerProfile',
                fn ($query) => $query->whereJsonContains('tags', $request->string('tag')->value()),
            ))
            ->orderByDesc('ltv')
            ->get();

        return Inertia::render('Admin/CRM/Customers/Index', [
            'customers' => $customers->map(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'ltv' => number_format((float) ($customer->ltv ?? 0), 2, '.', ''),
                'visits' => $customer->completed_visits,
                'no_show_count' => $customer->no_show_count,
                'loyalty_points' => $customer->customerProfile->loyalty_points,
                'tags' => $customer->customerProfile->tags ?? [],
                'is_blacklisted' => $customer->customerProfile->is_blacklisted,
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'tag' => $request->string('tag')->value() ?: null,
            ],
        ]);
    }

    /**
     * A real Inertia page (`Show.tsx`), not a JSON-fetched drawer like the other Phase 11 sub-steps —
     * the Customer 360 view's header stats + two tabs (Activity Timeline, Preferences & Notes) is
     * substantial enough to warrant its own navigable page/URL rather than an overlay on the list.
     */
    public function show(User $customer): Response
    {
        $this->authorize('view', $customer->customerProfile ?? new CustomerProfile(['user_id' => $customer->id]));

        $profile = $customer->customerProfile()->firstOrCreate([], ['user_id' => $customer->id]);

        $bookings = $customer->bookings()->with(['staff.user:id,name'])->orderByDesc('starts_at')->get();
        $completedBookings = $bookings->where('status', 'completed');

        return Inertia::render('Admin/CRM/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'stats' => [
                'ltv' => number_format((float) $completedBookings->sum('total'), 2, '.', ''),
                'total_appointments' => $bookings->count(),
                'completed_appointments' => $completedBookings->count(),
                'no_show_count' => $bookings->where('status', 'no_show')->count(),
                'cancelled_count' => $bookings->where('status', 'cancelled')->count(),
                'average_rating' => round((float) ($customer->reviews()->approved()->avg('rating') ?? 0), 1),
                'loyalty_points' => $profile->loyalty_points,
            ],
            'profile' => [
                'notes' => $profile->notes,
                'tags' => $profile->tags ?? [],
                'is_blacklisted' => $profile->is_blacklisted,
                'marketing_opt_in' => $profile->marketing_opt_in,
            ],
            'timeline' => $this->buildTimeline($customer, $bookings)->values(),
            'redemptions' => DealRedemption::where('customer_id', $customer->id)
                ->with('deal:id,title,code')
                ->latest('redeemed_at')
                ->get()
                ->map(fn (DealRedemption $redemption) => [
                    'id' => $redemption->id,
                    'deal_title' => $redemption->deal?->title,
                    'code_used' => $redemption->code_used,
                    'discount_amount' => (string) $redemption->discount_amount,
                    'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
                ]),
        ]);
    }

    public function updateNotes(UpdateCustomerNotesRequest $request, User $customer): RedirectResponse
    {
        $profile = $customer->customerProfile()->firstOrCreate([], ['user_id' => $customer->id]);

        $profile->update($request->validated());

        return back()->with('success', 'Customer notes updated.');
    }

    /**
     * A lightweight GDPR-style data export — the consolidated profile as a downloadable JSON file.
     * Deliberately does not implement a "delete my data" flow; not requested by this sub-step.
     */
    public function exportData(User $customer): JsonResponse
    {
        $this->authorize('view', $customer->customerProfile ?? new CustomerProfile(['user_id' => $customer->id]));

        $profile = $customer->customerProfile;
        $bookings = $customer->bookings;

        $export = [
            'customer' => $customer->only(['id', 'name', 'email', 'phone']),
            'profile' => $profile?->only(['dob', 'gender', 'notes', 'tags', 'loyalty_points', 'no_show_count', 'total_spent', 'visits']),
            'bookings' => $bookings->map(fn ($booking) => $booking->only(['id', 'code', 'status', 'starts_at', 'total']))->all(),
            'reviews' => $customer->reviews()->get()->map(fn (Review $review) => $review->only(['id', 'rating', 'title', 'body', 'status']))->all(),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->json($export)
            ->header('Content-Disposition', "attachment; filename=customer-{$customer->id}-export.json");
    }

    private function buildTimeline(User $customer, Collection $bookings): Collection
    {
        $timeline = collect();

        $bookings->each(fn ($booking) => $timeline->push([
            'type' => 'appointment',
            'label' => "Appointment ({$booking->status})" . ($booking->staff?->user?->name ? " with {$booking->staff->user->name}" : ''),
            'date' => $booking->starts_at?->toIso8601String(),
        ]));

        $customer->sales()->get()->each(fn ($sale) => $timeline->push([
            'type' => 'purchase',
            'label' => 'POS purchase — ' . CurrencyFormatter::format($sale->total),
            'date' => $sale->created_at?->toIso8601String(),
        ]));

        $customer->reviews()->get()->each(fn (Review $review) => $timeline->push([
            'type' => 'review',
            'label' => "Left a {$review->rating}-star review ({$review->status})",
            'date' => $review->created_at?->toIso8601String(),
        ]));

        NotificationLog::where('notifiable_type', User::class)
            ->where('notifiable_id', $customer->id)
            ->get()
            ->each(fn (NotificationLog $log) => $timeline->push([
                'type' => 'notification',
                'label' => "Notification sent via {$log->channel} ({$log->status})",
                'date' => $log->created_at?->toIso8601String(),
            ]));

        // Lead/Message have no FK to users (both are pre-account capture forms) — matched by email
        // as a best-effort heuristic, not a guaranteed-accurate join.
        if ($customer->email) {
            Lead::where('email', $customer->email)->get()->each(fn (Lead $lead) => $timeline->push([
                'type' => 'lead',
                'label' => "Lead created (source: {$lead->source}, status: {$lead->status})",
                'date' => $lead->created_at?->toIso8601String(),
            ]));

            Message::where('email', $customer->email)->get()->each(fn (Message $message) => $timeline->push([
                'type' => 'message',
                'label' => 'Sent a contact message' . ($message->subject ? ": \"{$message->subject}\"" : ''),
                'date' => $message->created_at?->toIso8601String(),
            ]));
        }

        return $timeline->sortByDesc('date')->take(50);
    }
}
