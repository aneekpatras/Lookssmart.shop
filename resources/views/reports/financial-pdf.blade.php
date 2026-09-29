<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1d1d1d; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .subtitle { color: #6d655f; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ece1d6; }
        th { background: #faf6f1; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpis { width: 100%; margin-bottom: 24px; }
        .kpis td { border: none; padding: 8px 12px; }
        .kpi-label { color: #6d655f; font-size: 10px; text-transform: uppercase; }
        .kpi-value { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Looks Smart Beauty Salon — Financial Report</h1>
    <p class="subtitle">{{ $from }} &ndash; {{ $to }}</p>

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Gross Revenue</div>
                <div class="kpi-value">${{ $summary['gross_revenue'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Net Revenue</div>
                <div class="kpi-value">${{ $summary['net_revenue'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Avg. Order Value</div>
                <div class="kpi-value">${{ $summary['average_order_value'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Transactions</div>
                <div class="kpi-value">{{ $summary['total_transactions'] }}</div>
            </td>
        </tr>
    </table>

    <table>
        <tr><th>Discounts Applied</th><th>Tax Collected</th></tr>
        <tr><td>${{ $summary['discounts'] }}</td><td>${{ $summary['tax'] }}</td></tr>
    </table>

    <h3>Top Services</h3>
    <table>
        <tr><th>Service</th><th>Bookings</th><th>Revenue</th></tr>
        @forelse ($topServices as $service)
            <tr><td>{{ $service['name'] }}</td><td>{{ $service['bookings_count'] }}</td><td>${{ $service['revenue'] }}</td></tr>
        @empty
            <tr><td colspan="3">No completed bookings in this range.</td></tr>
        @endforelse
    </table>

    <h3>Staff Performance</h3>
    <table>
        <tr><th>Staff</th><th>Bookings</th><th>Revenue</th><th>Commission</th></tr>
        @forelse ($staffPerformance as $staff)
            <tr><td>{{ $staff['name'] }}</td><td>{{ $staff['bookings_count'] }}</td><td>${{ $staff['revenue'] }}</td><td>${{ $staff['commission'] }}</td></tr>
        @empty
            <tr><td colspan="4">No completed bookings in this range.</td></tr>
        @endforelse
    </table>

    <h3>Payment Method Split</h3>
    <table>
        <tr><th>Method</th><th>Total</th></tr>
        @forelse ($paymentMethodSplit as $method)
            <tr><td>{{ $method['method'] }}</td><td>${{ $method['total'] }}</td></tr>
        @empty
            <tr><td colspan="2">No payment records yet.</td></tr>
        @endforelse
    </table>
</body>
</html>
