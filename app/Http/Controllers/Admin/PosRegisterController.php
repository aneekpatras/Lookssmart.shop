<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashMovementRequest;
use App\Http\Requests\Admin\CloseRegisterRequest;
use App\Http\Requests\Admin\OpenRegisterRequest;
use App\Models\CashMovement;
use App\Models\CashRegisterShift;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 12 sub-step 2: register session lifecycle (open/close/cash movements) and the Z-report. The
 * actual sale-ringing/checkout terminal (cart, line items, taking a payment) is a separate, later
 * Phase 12 sub-step — this one is purely the drawer/shift/reconciliation layer, matching its own
 * spec's endpoint list exactly (status/open/close/addCash/removeCash/zReport, nothing about carts).
 *
 * Only one register shift is ever `open` at a time (a single physical drawer) — enforced in open()
 * and checked in status(). `Payment.method` values are grouped as-is for the Z-report breakdown, not
 * hardcoded to a fixed list, so whatever methods actually get recorded (once the checkout sub-step
 * exists) show up correctly; only `cash` payments count toward the drawer reconciliation math, since
 * card/online tender was never physically in the drawer to begin with.
 */
class PosRegisterController extends Controller
{
    public function status(): Response
    {
        abort_unless(request()->user()->can('viewAny', CashRegisterShift::class), 403);

        $shift = CashRegisterShift::open()->with('staff:id,name')->latest('opened_at')->first();

        return Inertia::render('Admin/POS/Index', [
            'shift' => $shift ? $this->shiftData($shift) : null,
            'recentShifts' => CashRegisterShift::where('status', 'closed')
                ->with('staff:id,name')
                ->latest('closed_at')
                ->limit(10)
                ->get()
                ->map(fn (CashRegisterShift $shift) => $this->shiftData($shift)),
        ]);
    }

    public function open(OpenRegisterRequest $request): RedirectResponse
    {
        abort_if(CashRegisterShift::open()->exists(), 409, 'A register is already open. Close it before opening a new one.');

        CashRegisterShift::create([
            'staff_id' => $request->user()->id,
            'opening_float' => $request->validated('opening_float'),
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return back()->with('success', 'Register opened.');
    }

    public function addCash(CashMovementRequest $request, CashRegisterShift $shift): RedirectResponse
    {
        abort_unless($shift->status === 'open', 409, 'This register shift is already closed.');

        $shift->cashMovements()->create([
            'staff_id' => $request->user()->id,
            'type' => 'deposit',
            'amount' => $request->validated('amount'),
            'reason' => $request->validated('reason'),
        ]);

        return back()->with('success', 'Cash added to drawer.');
    }

    public function removeCash(CashMovementRequest $request, CashRegisterShift $shift): RedirectResponse
    {
        abort_unless($shift->status === 'open', 409, 'This register shift is already closed.');

        $amount = (float) $request->validated('amount');
        $expectedCash = $this->expectedCashTotal($shift);

        abort_if($amount > $expectedCash, 422, 'Cannot remove more cash than is currently expected in the drawer.');

        $shift->cashMovements()->create([
            'staff_id' => $request->user()->id,
            'type' => 'withdrawal',
            'amount' => $amount,
            'reason' => $request->validated('reason'),
        ]);

        return back()->with('success', 'Cash removed from drawer.');
    }

    public function close(CloseRegisterRequest $request, CashRegisterShift $shift): RedirectResponse
    {
        abort_unless($shift->status === 'open', 409, 'This register shift is already closed.');

        $expectedCash = $this->expectedCashTotal($shift);
        $countedTotal = (float) $request->validated('counted_total');

        $shift->update([
            'closing_float' => $countedTotal,
            'expected_total' => $expectedCash,
            'counted_total' => $countedTotal,
            'variance' => round($countedTotal - $expectedCash, 2),
            'status' => 'closed',
            'closed_at' => now(),
            'notes' => $request->validated('notes'),
        ]);

        return back()->with('success', 'Register closed.');
    }

    /**
     * Returns JSON, not an Inertia page — the printable Z-Report view fetches this directly (and can
     * be re-fetched later for reprinting), same detail-endpoint pattern as every other Phase 11/12
     * admin sub-step this session.
     */
    public function zReport(CashRegisterShift $shift): JsonResponse
    {
        abort_unless(request()->user()->can('view', $shift), 403);
        abort_unless($shift->status === 'closed', 409, 'The Z-Report is generated once a shift is closed.');

        return response()->json([
            'shift' => $this->shiftData($shift),
            'movements' => $shift->cashMovements()->with('staff:id,name')->orderBy('created_at')->get()
                ->map(fn (CashMovement $movement) => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'amount' => (string) $movement->amount,
                    'reason' => $movement->reason,
                    'staff' => $movement->staff?->name,
                    'created_at' => $movement->created_at?->toIso8601String(),
                ]),
            'paymentBreakdown' => $this->paymentBreakdown($shift),
        ]);
    }

    /** @return array<string, mixed> */
    private function shiftData(CashRegisterShift $shift): array
    {
        return [
            'id' => $shift->id,
            'staff_name' => $shift->staff?->name,
            'opening_float' => (string) $shift->opening_float,
            'closing_float' => $shift->closing_float !== null ? (string) $shift->closing_float : null,
            'expected_total' => $shift->expected_total !== null ? (string) $shift->expected_total : null,
            'counted_total' => $shift->counted_total !== null ? (string) $shift->counted_total : null,
            'variance' => $shift->variance !== null ? (string) $shift->variance : null,
            'status' => $shift->status,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'notes' => $shift->notes,
            'current_expected_cash' => $shift->status === 'open' ? number_format($this->expectedCashTotal($shift), 2, '.', '') : null,
        ];
    }

    /**
     * opening float + cash deposits - cash withdrawals + succeeded cash payments recorded during the
     * shift window. This is the figure the physically-counted drawer is reconciled against at close.
     */
    private function expectedCashTotal(CashRegisterShift $shift): float
    {
        $deposits = (float) $shift->cashMovements()->where('type', 'deposit')->sum('amount');
        $withdrawals = (float) $shift->cashMovements()->where('type', 'withdrawal')->sum('amount');
        $breakdown = $this->paymentBreakdown($shift);
        $cashPayments = (float) ($breakdown['cash'] ?? 0);

        return round((float) $shift->opening_float + $deposits - $withdrawals + $cashPayments, 2);
    }

    /** @return array<string, string> */
    private function paymentBreakdown(CashRegisterShift $shift): array
    {
        $windowEnd = $shift->closed_at ?? now();

        return Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('created_at', [$shift->opened_at, $windowEnd])
            ->select('method', DB::raw('SUM(amount) as total'))
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total) => number_format((float) $total, 2, '.', ''))
            ->all();
    }
}
