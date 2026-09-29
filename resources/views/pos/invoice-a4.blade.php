<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale['sale_number'] }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1d1d1d; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .subtitle { color: #6d655f; margin-bottom: 4px; }
        .meta { width: 100%; margin: 20px 0; }
        .meta td { padding: 2px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items th, table.items td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ece1d6; }
        table.items th { background: #faf6f1; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        table.items td.num, table.items th.num { text-align: right; }
        table.totals { width: 260px; margin-left: auto; }
        table.totals td { padding: 3px 0; }
        table.totals td.num { text-align: right; }
        table.totals tr.grand td { font-weight: bold; font-size: 14px; border-top: 1px solid #1d1d1d; padding-top: 6px; }
        .footer { margin-top: 40px; text-align: center; color: #6d655f; font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ $sale['business']['name'] }}</h1>
    @if ($sale['business']['address'])
        <p class="subtitle">{{ $sale['business']['address'] }}</p>
    @endif
    @if ($sale['business']['phone'])
        <p class="subtitle">{{ $sale['business']['phone'] }}</p>
    @endif

    <table class="meta">
        <tr>
            <td><strong>Invoice #:</strong> {{ $sale['sale_number'] }}</td>
            <td><strong>Date:</strong> {{ \Illuminate\Support\Carbon::parse($sale['created_at'])->format('d M Y, h:i A') }}</td>
        </tr>
        <tr>
            <td><strong>Customer:</strong> {{ $sale['customer_name'] ?? 'Walk-in Customer' }}</td>
            <td><strong>Cashier:</strong> {{ $sale['created_by'] ?? 'Staff' }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Status:</strong> {{ ucfirst($sale['status']) }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Service</th>
                <th>Staff</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale['items'] as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ $item['staff'] ?? '—' }}</td>
                    <td class="num">{{ $item['quantity'] }}</td>
                    <td class="num">Rs. {{ number_format((float) $item['unit_price'], 2) }}</td>
                    <td class="num">Rs. {{ number_format((float) $item['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">Rs. {{ number_format((float) $sale['subtotal'], 2) }}</td></tr>
        @if ((float) $sale['discount'] > 0)
            <tr><td>Discount{{ $sale['discount_percent'] ? ' (' . rtrim(rtrim($sale['discount_percent'], '0'), '.') . '%)' : '' }}</td><td class="num">-Rs. {{ number_format((float) $sale['discount'], 2) }}</td></tr>
        @endif
        <tr><td>Tax{{ $sale['tax_rate_percent'] ? ' (' . rtrim(rtrim($sale['tax_rate_percent'], '0'), '.') . '%)' : '' }}</td><td class="num">Rs. {{ number_format((float) $sale['tax'], 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">Rs. {{ number_format((float) $sale['total'], 2) }}</td></tr>
        @foreach ($sale['payments'] as $payment)
            <tr><td>Paid ({{ ucfirst(str_replace('_', ' ', $payment['method'])) }})</td><td class="num">Rs. {{ number_format((float) $payment['amount'], 2) }}</td></tr>
            @if ($payment['tendered_amount'])
                <tr><td>Cash Tendered</td><td class="num">Rs. {{ number_format((float) $payment['tendered_amount'], 2) }}</td></tr>
                <tr><td>Change Returned</td><td class="num">Rs. {{ number_format((float) $payment['change'], 2) }}</td></tr>
            @endif
        @endforeach
    </table>

    <div class="footer">
        <p>Thank you! Visit again.</p>
        <p>Software developed by Aneek | 03199154505</p>
    </div>
</body>
</html>
