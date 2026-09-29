<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * POS sub-step 4: a standalone, filterable ledger of finalized sales — separate from HeldSales
 * (`status = 'open'`, still in progress) and from ReportController's aggregate financial dashboard.
 * `withTrashed()` throughout so a voided sale still shows up (with its status badge) instead of
 * silently vanishing from the history an admin is trying to audit.
 */
class PosSalesHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->filteredQuery($request)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/POS/SalesHistory', [
            'sales' => $sales->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer_name' => $sale->customer?->name,
                'cashier_name' => $sale->createdBy?->name,
                'total' => (string) $sale->total,
                'status' => $sale->status,
                'created_at' => $sale->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['invoice', 'from', 'to', 'customer_id', 'cashier_id', 'status']),
            'customers' => User::query()
                ->whereHas('sales')
                ->orderBy('name')
                ->get(['id', 'name']),
            'cashiers' => User::query()
                ->whereHas('createdSales')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->filteredQuery($request)->latest()->get();

        return ResponseFacade::streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Invoice #', 'Date', 'Customer', 'Cashier', 'Subtotal', 'Discount', 'Tax', 'Total', 'Status']);

            foreach ($sales as $sale) {
                fputcsv($out, [
                    $sale->sale_number,
                    $sale->created_at?->format('Y-m-d H:i'),
                    $sale->customer?->name ?? 'Walk-in',
                    $sale->createdBy?->name,
                    $sale->subtotal,
                    $sale->discount,
                    $sale->tax,
                    $sale->total,
                    $sale->status,
                ]);
            }

            fclose($out);
        }, 'sales-history-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request)
    {
        return Sale::query()
            ->withTrashed()
            ->where('status', '!=', 'open')
            ->with(['customer:id,name', 'createdBy:id,name'])
            ->when($request->filled('invoice'), fn ($query) => $query->where('sale_number', 'like', '%' . $request->string('invoice') . '%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('cashier_id'), fn ($query) => $query->where('created_by', $request->integer('cashier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
    }
}
