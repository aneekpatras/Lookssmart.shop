import { Head, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import * as React from 'react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

type RangeKey = 'today' | 'this_week' | 'this_month' | 'custom';

interface Summary {
  gross_revenue: string;
  net_revenue: string;
  discounts: string;
  tax: string;
  total_transactions: number;
  average_order_value: string;
}

interface RevenuePoint {
  date: string;
  revenue: string;
}

interface PaymentMethodPoint {
  method: string;
  total: string;
}

interface ServiceRow {
  id: number;
  name: string;
  bookings_count: number;
  revenue: string;
}

interface StaffRow {
  id: number | null;
  name: string;
  bookings_count: number;
  revenue: string;
  commission: string;
}

interface ReportsIndexPageProps {
  summary: Summary;
  revenueTrend: RevenuePoint[];
  paymentMethodSplit: PaymentMethodPoint[];
  topServices: ServiceRow[];
  staffPerformance: StaffRow[];
  filters: { range: RangeKey; from: string; to: string };
}

const PIE_COLORS = ['#c9a66b', '#8a6a34', '#e8d5b5', '#4f4a46', '#d8c1a0'];

function KpiCard({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">{label}</p>
      </CardHeader>
      <CardContent>
        <p className="font-display text-ink text-3xl font-medium">{value}</p>
      </CardContent>
    </Card>
  );
}

function FiltersBar({ filters }: { filters: ReportsIndexPageProps['filters'] }) {
  const [from, setFrom] = React.useState(filters.from);
  const [to, setTo] = React.useState(filters.to);

  function apply(range: RangeKey, customFrom?: string, customTo?: string) {
    router.get(
      '/admin/reports',
      {
        range,
        from: range === 'custom' ? (customFrom ?? from) : undefined,
        to: range === 'custom' ? (customTo ?? to) : undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="space-y-1.5">
        <Label htmlFor="report-range">Date range</Label>
        <Select value={filters.range} onValueChange={(value) => apply(value as RangeKey)}>
          <SelectTrigger id="report-range" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="today">Today</SelectItem>
            <SelectItem value="this_week">This Week</SelectItem>
            <SelectItem value="this_month">This Month</SelectItem>
            <SelectItem value="custom">Custom</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {filters.range === 'custom' && (
        <>
          <div className="space-y-1.5">
            <Label htmlFor="report-from">From</Label>
            <Input id="report-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="report-to">To</Label>
            <Input id="report-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </div>
          <Button type="button" variant="outline" onClick={() => apply('custom', from, to)}>
            Apply
          </Button>
        </>
      )}

      <div className="ml-auto flex gap-2">
        <Button asChild variant="outline">
          <a href={`/admin/reports/export/csv?range=${filters.range}&from=${filters.from}&to=${filters.to}`}>
            <Download className="size-4" />
            Export CSV
          </a>
        </Button>
        <Button asChild variant="outline">
          <a href={`/admin/reports/export/pdf?range=${filters.range}&from=${filters.from}&to=${filters.to}`}>
            <FileText className="size-4" />
            Export PDF
          </a>
        </Button>
      </div>
    </div>
  );
}

export default function ReportsIndex({
  summary,
  revenueTrend,
  paymentMethodSplit,
  topServices,
  staffPerformance,
  filters,
}: ReportsIndexPageProps) {
  return (
    <>
      <Head title="Reports" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Financial Reports</h1>
          <p className="text-ink-muted text-sm">
            Revenue, service trends, and staff performance for the selected period.
          </p>
        </div>

        <FiltersBar filters={filters} />

        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <KpiCard label="Gross Revenue" value={formatCurrency(Number(summary.gross_revenue))} />
          <KpiCard label="Net Revenue" value={formatCurrency(Number(summary.net_revenue))} />
          <KpiCard label="Avg. Order Value" value={formatCurrency(Number(summary.average_order_value))} />
          <KpiCard label="Total Transactions" value={String(summary.total_transactions)} />
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Revenue Trend</CardTitle>
            </CardHeader>
            <CardContent>
              {revenueTrend.length === 0 ? (
                <EmptyState title="No revenue yet" description="No completed bookings in this range." />
              ) : (
                <ResponsiveContainer width="100%" height={260}>
                  <AreaChart data={revenueTrend.map((p) => ({ ...p, revenue: Number(p.revenue) }))}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#ece1d6" />
                    <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                    <Area type="monotone" dataKey="revenue" stroke="#c9a66b" fill="#c9a66b" fillOpacity={0.25} />
                  </AreaChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Payment Method Split</CardTitle>
            </CardHeader>
            <CardContent>
              {paymentMethodSplit.length === 0 ? (
                <EmptyState
                  title="No payment data yet"
                  description="Payment recording is part of a later Phase 12 sub-step (the POS terminal itself)."
                />
              ) : (
                <ResponsiveContainer width="100%" height={260}>
                  <PieChart>
                    <Pie
                      data={paymentMethodSplit.map((p) => ({ ...p, total: Number(p.total) }))}
                      dataKey="total"
                      nameKey="method"
                      outerRadius={90}
                    >
                      {paymentMethodSplit.map((_, index) => (
                        <Cell key={index} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Top 5 Services</CardTitle>
          </CardHeader>
          <CardContent>
            {topServices.length === 0 ? (
              <EmptyState title="No services booked" description="No completed bookings in this range." />
            ) : (
              <ResponsiveContainer width="100%" height={240}>
                <BarChart data={topServices.map((s) => ({ ...s, revenue: Number(s.revenue) }))} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" stroke="#ece1d6" />
                  <XAxis type="number" tick={{ fontSize: 11 }} />
                  <YAxis type="category" dataKey="name" width={140} tick={{ fontSize: 11 }} />
                  <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                  <Bar dataKey="revenue" fill="#c9a66b" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Staff Performance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-accent-50/60">
                  <tr>
                    <th className="p-3 text-left">Staff</th>
                    <th className="p-3 text-left">Bookings</th>
                    <th className="p-3 text-left">Revenue</th>
                    <th className="p-3 text-left">Commission</th>
                  </tr>
                </thead>
                <tbody className="divide-border-soft divide-y">
                  {staffPerformance.map((staff) => (
                    <tr key={staff.id ?? staff.name}>
                      <td className="p-3">{staff.name}</td>
                      <td className="p-3">{staff.bookings_count}</td>
                      <td className="p-3">{formatCurrency(Number(staff.revenue))}</td>
                      <td className="p-3">{formatCurrency(Number(staff.commission))}</td>
                    </tr>
                  ))}
                  {staffPerformance.length === 0 && (
                    <tr>
                      <td colSpan={4} className="text-ink-muted p-6 text-center text-sm">
                        No completed bookings in this range.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

ReportsIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
