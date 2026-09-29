<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 12 sub-step 1: financial/POS analytics. `bookings.total`/`discount`/`tax` (Phase 7,
 * `PriceQuoteService`) are real, populated, already-tested figures — they're the primary revenue
 * source here, not `sales`/`payments` (Phase 2 schema, but genuinely never written to by any code
 * yet — confirmed by grep — POS transaction recording is a later Phase 12 sub-step). The payment-
 * method split below queries the real `payments` table with a real, correct query; it will show
 * empty until that later sub-step exists, which is the honest state, not a placeholder fabrication.
 *
 * Revenue math, derived from `PriceQuoteService`'s own formula (subtotal → discount → tax → total):
 *   total = (subtotal - discount) + tax,  so:
 *   net revenue (post-discount, pre-tax)  = SUM(total) - SUM(tax)
 *   gross revenue (pre-discount subtotal) = net revenue + SUM(discount)
 * "Net Profit" was deliberately NOT used as a KPI label — there's no cost/expense data anywhere in
 * this schema to compute real profit, only post-discount revenue. Labeled "Net Revenue" instead, to
 * avoid claiming a number this app cannot actually back.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        [$from, $to, $rangeKey] = $this->resolveRange($request);
        $report = $this->buildReport($from, $to);

        return Inertia::render('Admin/Reports/Index', [
            ...$report,
            'filters' => [
                'range' => $rangeKey,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        [$from, $to] = $this->resolveRange($request);
        $report = $this->buildReport($from, $to);

        return ResponseFacade::streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Gross Revenue', $report['summary']['gross_revenue']]);
            fputcsv($out, ['Net Revenue', $report['summary']['net_revenue']]);
            fputcsv($out, ['Discounts Applied', $report['summary']['discounts']]);
            fputcsv($out, ['Tax Collected', $report['summary']['tax']]);
            fputcsv($out, ['Total Transactions', $report['summary']['total_transactions']]);
            fputcsv($out, ['Average Order Value', $report['summary']['average_order_value']]);
            fputcsv($out, []);

            fputcsv($out, ['Top Services', 'Bookings', 'Revenue']);
            foreach ($report['topServices'] as $service) {
                fputcsv($out, [$service['name'], $service['bookings_count'], $service['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Staff', 'Bookings', 'Revenue', 'Commission']);
            foreach ($report['staffPerformance'] as $staff) {
                fputcsv($out, [$staff['name'], $staff['bookings_count'], $staff['revenue'], $staff['commission']]);
            }

            fclose($out);
        }, 'financial-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        [$from, $to] = $this->resolveRange($request);
        $report = $this->buildReport($from, $to);

        return Pdf::loadView('reports.financial-pdf', [
            ...$report,
            'from' => $from->toFormattedDateString(),
            'to' => $to->toFormattedDateString(),
        ])->download('financial-report.pdf');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function resolveRange(Request $request): array
    {
        $rangeKey = $request->string('range')->value() ?: 'this_month';
        $now = CarbonImmutable::now();

        [$from, $to] = match ($rangeKey) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'custom' => [
                $request->filled('from') ? CarbonImmutable::parse($request->string('from')->value())->startOfDay() : $now->startOfMonth(),
                $request->filled('to') ? CarbonImmutable::parse($request->string('to')->value())->endOfDay() : $now->endOfDay(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return [$from, $to, $rangeKey];
    }

    /** @return array<string, mixed> */
    private function buildReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bookings = Booking::query()
            ->where('status', 'completed')
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'staff_id', 'total', 'discount', 'tax', 'starts_at']);

        $totalSum = (float) $bookings->sum('total');
        $taxSum = (float) $bookings->sum('tax');
        $discountSum = (float) $bookings->sum('discount');
        $netRevenue = $totalSum - $taxSum;
        $grossRevenue = $netRevenue + $discountSum;
        $transactionCount = $bookings->count();

        return [
            'summary' => [
                'gross_revenue' => number_format($grossRevenue, 2, '.', ''),
                'net_revenue' => number_format($netRevenue, 2, '.', ''),
                'discounts' => number_format($discountSum, 2, '.', ''),
                'tax' => number_format($taxSum, 2, '.', ''),
                'total_transactions' => $transactionCount,
                'average_order_value' => number_format($transactionCount > 0 ? $totalSum / $transactionCount : 0, 2, '.', ''),
            ],
            'revenueTrend' => $bookings
                ->groupBy(fn (Booking $booking) => $booking->starts_at->toDateString())
                ->map(fn ($group, $date) => ['date' => $date, 'revenue' => number_format((float) $group->sum('total'), 2, '.', '')])
                ->values()
                ->sortBy('date')
                ->values(),
            'paymentMethodSplit' => Payment::query()
                ->where('status', 'succeeded')
                ->whereHas('booking', fn ($query) => $query->where('status', 'completed')->whereBetween('starts_at', [$from, $to]))
                ->selectRaw('method, SUM(amount) as total')
                ->groupBy('method')
                ->get()
                ->map(fn (Payment $payment) => ['method' => $payment->method, 'total' => number_format((float) $payment->total, 2, '.', '')])
                ->values(),
            'topServices' => BookingItem::query()
                ->whereHas('booking', fn ($query) => $query->where('status', 'completed')->whereBetween('starts_at', [$from, $to]))
                ->with('service:id,name,service_category_id')
                ->get()
                ->groupBy('service_id')
                ->map(fn ($items, $serviceId) => [
                    'id' => $serviceId,
                    'name' => $items->first()->service?->name ?? 'Unknown service',
                    'bookings_count' => $items->count(),
                    'revenue' => number_format((float) $items->sum('price_snapshot'), 2, '.', ''),
                ])
                ->sortByDesc(fn ($service) => (float) $service['revenue'])
                ->take(5)
                ->values(),
            'staffPerformance' => Booking::query()
                ->where('status', 'completed')
                ->whereBetween('starts_at', [$from, $to])
                ->whereNotNull('staff_id')
                ->with('staff.user:id,name')
                ->get()
                ->groupBy('staff_id')
                ->map(function ($group) {
                    $staff = $group->first()->staff;
                    $revenue = (float) $group->sum('total');
                    $commissionRate = (float) ($staff?->commission_rate ?? 0);

                    return [
                        'id' => $staff?->id,
                        'name' => $staff?->user?->name ?? 'Unassigned',
                        'bookings_count' => $group->count(),
                        'revenue' => number_format($revenue, 2, '.', ''),
                        'commission' => number_format($revenue * $commissionRate / 100, 2, '.', ''),
                    ];
                })
                ->sortByDesc(fn ($staff) => (float) $staff['revenue'])
                ->values(),
        ];
    }
}
