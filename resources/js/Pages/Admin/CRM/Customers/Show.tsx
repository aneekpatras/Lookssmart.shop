import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

interface TimelineEntry {
  type: 'appointment' | 'purchase' | 'review' | 'notification' | 'lead' | 'message';
  label: string;
  date: string | null;
}

interface RedemptionEntry {
  id: number;
  deal_title: string | null;
  code_used: string | null;
  discount_amount: string;
  redeemed_at: string | null;
}

interface CustomerDetail {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string | null;
}

interface Stats {
  ltv: string;
  total_appointments: number;
  completed_appointments: number;
  no_show_count: number;
  cancelled_count: number;
  average_rating: number;
  loyalty_points: number;
}

interface ProfileData {
  notes: string | null;
  tags: string[];
  is_blacklisted: boolean;
  marketing_opt_in: boolean;
}

interface CustomerShowPageProps {
  customer: CustomerDetail;
  stats: Stats;
  profile: ProfileData;
  timeline: TimelineEntry[];
  redemptions: RedemptionEntry[];
}

const SUGGESTED_TAGS = ['VIP', 'High No-Show Risk', 'Frequent Visitor', 'Price Sensitive'];

const TYPE_LABEL: Record<TimelineEntry['type'], string> = {
  appointment: 'Appointment',
  purchase: 'Purchase',
  review: 'Review',
  notification: 'Notification',
  lead: 'Lead',
  message: 'Message',
};

function StatCard({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <Card>
      <CardContent className="p-4">
        <p className="text-ink-muted text-xs font-semibold tracking-wide uppercase">{label}</p>
        <p className="font-display text-ink mt-1 text-2xl">{value}</p>
      </CardContent>
    </Card>
  );
}

function ActivityTimeline({ timeline }: { timeline: TimelineEntry[] }) {
  if (timeline.length === 0) {
    return <p className="text-ink-muted text-sm">No activity recorded yet.</p>;
  }

  return (
    <ul className="space-y-3">
      {timeline.map((entry, index) => (
        <li key={index} className="border-border-soft flex items-start justify-between gap-4 border-b pb-3">
          <div>
            <Badge variant="outline" className="mb-1">
              {TYPE_LABEL[entry.type]}
            </Badge>
            <p className="text-sm">{entry.label}</p>
          </div>
          <span className="text-ink-muted shrink-0 text-xs">
            {entry.date ? new Date(entry.date).toLocaleDateString() : ''}
          </span>
        </li>
      ))}
    </ul>
  );
}

function PreferencesAndNotes({ customerId, profile }: { customerId: number; profile: ProfileData }) {
  const { data, setData, patch, processing } = useForm({
    notes: profile.notes ?? '',
    tags: profile.tags,
    is_blacklisted: profile.is_blacklisted,
  });

  function toggleTag(tag: string) {
    setData('tags', data.tags.includes(tag) ? data.tags.filter((t) => t !== tag) : [...data.tags, tag]);
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    patch(`/admin/customers/${customerId}/notes`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Customer notes updated.'),
    });
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      <div className="space-y-1.5">
        <Label>Flags</Label>
        <div className="flex flex-wrap gap-2">
          {SUGGESTED_TAGS.map((tag) => (
            <button
              key={tag}
              type="button"
              onClick={() => toggleTag(tag)}
              className={cn(
                'rounded-full border px-3 py-1 text-xs font-medium',
                data.tags.includes(tag)
                  ? 'border-accent-500 bg-accent-100 text-accent-700'
                  : 'border-border-soft text-ink-muted',
              )}
              aria-pressed={data.tags.includes(tag)}
            >
              {tag}
            </button>
          ))}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Switch
          id="is_blacklisted"
          checked={data.is_blacklisted}
          onCheckedChange={(checked) => setData('is_blacklisted', checked === true)}
        />
        <Label htmlFor="is_blacklisted">Blacklisted</Label>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="notes">Internal notes</Label>
        <Textarea
          id="notes"
          rows={5}
          value={data.notes}
          onChange={(e) => setData('notes', e.target.value)}
          placeholder="Private notes visible only to staff..."
        />
      </div>

      <Button type="submit" disabled={processing}>
        Save
      </Button>
    </form>
  );
}

export default function CustomerShow({ customer, stats, profile, timeline, redemptions }: CustomerShowPageProps) {
  const [tab, setTab] = React.useState<'timeline' | 'preferences'>('timeline');

  return (
    <>
      <Head title={customer.name} />
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <Button asChild variant="ghost" size="sm">
            <Link href="/admin/customers">
              <ArrowLeft className="size-4" />
              Back to customers
            </Link>
          </Button>
          <Button asChild variant="outline" size="sm">
            <a href={`/admin/customers/${customer.id}/export`}>
              <Download className="size-4" />
              Export data
            </a>
          </Button>
        </div>

        <Card>
          <CardContent className="space-y-4 p-6">
            <div>
              <h1 className="font-display text-ink text-2xl font-medium">{customer.name}</h1>
              <p className="text-ink-muted text-sm">
                {customer.email}
                {customer.phone ? ` · ${customer.phone}` : ''}
              </p>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <StatCard label="Lifetime value" value={formatCurrency(stats.ltv)} />
              <StatCard label="Visits" value={stats.completed_appointments} />
              <StatCard label="Avg. rating" value={stats.average_rating > 0 ? `${stats.average_rating} / 5` : '—'} />
              <StatCard label="Loyalty points" value={stats.loyalty_points} />
            </div>
            <div className="text-ink-muted flex flex-wrap gap-4 text-sm">
              <span>{stats.total_appointments} total appointments</span>
              <span>{stats.no_show_count} no-shows</span>
              <span>{stats.cancelled_count} cancelled</span>
              <span>{redemptions.length} coupon redemption{redemptions.length === 1 ? '' : 's'}</span>
            </div>
          </CardContent>
        </Card>

        <div role="tablist" aria-label="Customer sections" className="border-border-soft flex gap-1 border-b pb-px">
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'timeline'}
            onClick={() => setTab('timeline')}
            className={cn(
              'rounded-t-md px-4 py-2 text-sm font-medium',
              tab === 'timeline' ? 'border-accent-500 text-accent-700 border-b-2' : 'text-ink-muted hover:text-ink',
            )}
          >
            Activity Timeline
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'preferences'}
            onClick={() => setTab('preferences')}
            className={cn(
              'rounded-t-md px-4 py-2 text-sm font-medium',
              tab === 'preferences' ? 'border-accent-500 text-accent-700 border-b-2' : 'text-ink-muted hover:text-ink',
            )}
          >
            Preferences &amp; Notes
          </button>
        </div>

        <Card>
          <CardContent className="p-6">
            {tab === 'timeline' ? (
              <ActivityTimeline timeline={timeline} />
            ) : (
              <PreferencesAndNotes customerId={customer.id} profile={profile} />
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

CustomerShow.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
