import { Head } from '@inertiajs/react';
import { AlertTriangle, Calendar, Info, TrendingUp, Users } from 'lucide-react';
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface Kpis {
  bookings_today: number;
  revenue_today: number;
  revenue_mtd: number;
  new_leads_today: number;
  pending_reviews: number;
}

interface RevenuePoint {
  date: string;
  revenue: number;
}

interface ScheduleItem {
  id: number;
  code: string;
  starts_at: string | null;
  status: string;
  staff_name: string | null;
  services: string[];
  customer_name: string | null;
}

interface Alert {
  level: 'info' | 'warning';
  message: string;
}

interface DashboardPageProps {
  kpis: Kpis;
  revenueChart: RevenuePoint[];
  todaySchedule: ScheduleItem[];
  alerts: Alert[];
}


function KpiCard({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescriptionLabel>{label}</CardDescriptionLabel>
      </CardHeader>
      <CardContent>
        <p className="font-display text-ink text-3xl font-medium">{value}</p>
      </CardContent>
    </Card>
  );
}

function CardDescriptionLabel({ children }: { children: React.ReactNode }) {
  return <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">{children}</p>;
}

const STATUS_VARIANT: Record<string, 'default' | 'accent' | 'success' | 'warning' | 'destructive'> =
  {
    pending: 'warning',
    confirmed: 'accent',
    checked_in: 'accent',
    completed: 'success',
    cancelled: 'destructive',
    no_show: 'destructive',
  };

export default function Dashboard({
  kpis,
  revenueChart,
  todaySchedule,
  alerts,
}: DashboardPageProps) {
  return (
    <>
      <Head title="Dashboard" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Dashboard</h1>
          <p className="text-ink-muted text-sm">
            Today&apos;s snapshot across bookings, revenue, and CRM.
          </p>
        </div>

        {alerts.length > 0 && (
          <div className="space-y-2">
            {alerts.map((alert, index) => (
              <div
                key={index}
                className={`flex items-center gap-2 rounded-lg border px-4 py-3 text-sm ${
                  alert.level === 'warning'
                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                    : 'border-accent-300 bg-accent-50 text-accent-700'
                }`}
              >
                {alert.level === 'warning' ? (
                  <AlertTriangle className="size-4 shrink-0" />
                ) : (
                  <Info className="size-4 shrink-0" />
                )}
                {alert.message}
              </div>
            ))}
          </div>
        )}

        <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
          <KpiCard label="Bookings today" value={String(kpis.bookings_today)} />
          <KpiCard label="Revenue today" value={formatCurrency(kpis.revenue_today)} />
          <KpiCard label="Revenue MTD" value={formatCurrency(kpis.revenue_mtd)} />
          <KpiCard label="New leads today" value={String(kpis.new_leads_today)} />
          <KpiCard label="Pending reviews" value={String(kpis.pending_reviews)} />
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <TrendingUp className="text-accent-600 size-4" />
              Revenue, last 30 days
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={revenueChart} margin={{ left: 0, right: 12, top: 8, bottom: 0 }}>
                  <defs>
                    <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#c9a66b" stopOpacity={0.35} />
                      <stop offset="100%" stopColor="#c9a66b" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e8e1d6" vertical={false} />
                  <XAxis
                    dataKey="date"
                    tickFormatter={(value: string) => new Date(value).getDate().toString()}
                    tick={{ fontSize: 12, fill: '#6b6157' }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <YAxis
                    tickFormatter={(value: number) => formatCurrency(value)}
                    tick={{ fontSize: 12, fill: '#6b6157' }}
                    axisLine={false}
                    tickLine={false}
                    width={64}
                  />
                  <Tooltip
                    formatter={(value) => formatCurrency(Number(value))}
                    labelFormatter={(value) => new Date(String(value)).toLocaleDateString()}
                    contentStyle={{ borderRadius: 8, borderColor: '#e8e1d6', fontSize: 13 }}
                  />
                  <Area
                    type="monotone"
                    dataKey="revenue"
                    stroke="#b08d4f"
                    strokeWidth={2}
                    fill="url(#revenueFill)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Calendar className="text-accent-600 size-4" />
              Today&apos;s schedule
            </CardTitle>
          </CardHeader>
          <CardContent>
            {todaySchedule.length === 0 ? (
              <EmptyState icon={Users} title="No bookings today" />
            ) : (
              <ul className="divide-border-soft divide-y">
                {todaySchedule.map((item) => (
                  <li key={item.id} className="flex items-center justify-between gap-4 py-3">
                    <div className="min-w-0">
                      <p className="text-ink truncate text-sm font-medium">
                        {item.customer_name ?? 'Guest'} —{' '}
                        {item.services.join(', ') || 'No services'}
                      </p>
                      <p className="text-ink-muted text-xs">
                        {item.starts_at
                          ? new Date(item.starts_at).toLocaleTimeString(undefined, {
                              hour: 'numeric',
                              minute: '2-digit',
                            })
                          : '—'}{' '}
                        with {item.staff_name ?? 'unassigned'}
                      </p>
                    </div>
                    <Badge variant={STATUS_VARIANT[item.status] ?? 'default'}>
                      {item.status.replace('_', ' ')}
                    </Badge>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Dashboard.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
