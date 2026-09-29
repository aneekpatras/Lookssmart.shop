<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 5 sub-step 3: KPI cards, a 30-day revenue chart, today's schedule, and an alerts area — all
 * against real seeded data, not mocks. No `->authorize()` gate here: this is the dashboard every
 * `/admin/*` role lands on (matches `adminNav.ts`'s `permissions: null` for Dashboard), so it only
 * ever shows counts, never another user's private record.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = today();

        return Inertia::render('Admin/Dashboard', [
            'kpis' => [
                'bookings_today' => Booking::whereDate('starts_at', $today)
                    ->whereNotIn('status', ['cancelled'])
                    ->count(),
                'revenue_today' => (float) Sale::whereDate('created_at', $today)
                    ->where('status', 'completed')
                    ->sum('total'),
                'revenue_mtd' => (float) Sale::where('created_at', '>=', $today->copy()->startOfMonth())
                    ->where('status', 'completed')
                    ->sum('total'),
                'new_leads_today' => Lead::whereDate('created_at', $today)->count(),
                'pending_reviews' => Review::where('status', 'pending')->count(),
            ],
            'revenueChart' => $this->last30DaysRevenue(),
            'todaySchedule' => $this->todaySchedule(),
            'alerts' => $this->alerts(),
        ]);
    }

    /**
     * @return array<int, array{date: string, revenue: float}>
     */
    private function last30DaysRevenue(): array
    {
        $start = today()->subDays(29);

        $byDay = Sale::where('created_at', '>=', $start)
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $days = [];

        for ($date = $start->copy(); $date->lte(today()); $date->addDay()) {
            $key = $date->toDateString();
            $days[] = ['date' => $key, 'revenue' => (float) ($byDay[$key] ?? 0)];
        }

        return $days;
    }

    /**
     * Eager-loads staff+service in one extra query each, regardless of how many bookings today has —
     * this is exactly the N+1 the Phase 5 spec's "Performance" line asks to guard against.
     *
     * @return array<int, array<string, mixed>>
     */
    private function todaySchedule(): array
    {
        return Booking::whereDate('starts_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->with(['staff.user:id,name', 'items.service:id,name', 'customer:id,name'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'code' => $booking->code,
                'starts_at' => $booking->starts_at?->toIso8601String(),
                'status' => $booking->status,
                'staff_name' => $booking->staff?->user?->name,
                'services' => $booking->items->map(fn ($item) => $item->service?->name)->filter()->values(),
                'customer_name' => $booking->customer_id
                    ? $booking->customer?->name
                    : $booking->guest_name,
            ])
            ->toArray();
    }

    /**
     * @return array<int, array{level: string, message: string}>
     */
    private function alerts(): array
    {
        $alerts = [];

        $pendingReviews = Review::where('status', 'pending')->count();
        if ($pendingReviews > 0) {
            $alerts[] = [
                'level' => 'info',
                'message' => "{$pendingReviews} review" . ($pendingReviews === 1 ? '' : 's') . ' awaiting moderation.',
            ];
        }

        $newLeads = Lead::where('status', 'new')->count();
        if ($newLeads > 0) {
            $alerts[] = [
                'level' => 'info',
                'message' => "{$newLeads} lead" . ($newLeads === 1 ? '' : 's') . ' not yet contacted.',
            ];
        }

        $noShowsToday = Booking::whereDate('starts_at', today())->where('status', 'no_show')->count();
        if ($noShowsToday > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "{$noShowsToday} no-show" . ($noShowsToday === 1 ? '' : 's') . ' today.',
            ];
        }

        return $alerts;
    }
}
