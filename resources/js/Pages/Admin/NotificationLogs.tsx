import { Head, router } from '@inertiajs/react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import AdminLayout from '@/Layouts/AdminLayout';

interface NotificationLogRow {
  id: number;
  channel: string;
  recipient: string;
  status: string;
  provider_ref: string | null;
  error: string | null;
  sent_at: string | null;
}

interface NotificationLogsProps {
  logs: { data: NotificationLogRow[]; current_page: number; last_page: number; total: number };
  filters: { channel: string | null; status: string | null };
}

export default function NotificationLogs({ logs, filters }: NotificationLogsProps) {
  function filter(key: 'channel' | 'status', value: string) {
    router.get('/admin/notification-logs', { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout>
      <Head title="Notification Logs" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Notification Logs</h1>
          <p className="text-ink-muted text-sm">Delivery history for email, SMS, WhatsApp, and scheduled notifications.</p>
        </div>
        <div className="flex gap-3">
          <select aria-label="Channel" value={filters.channel ?? ''} onChange={(event) => filter('channel', event.target.value)}>
            <option value="">All channels</option>
            <option value="mail">Email</option>
            <option value="sms">SMS</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="reminder">Reminder</option>
          </select>
          <select aria-label="Status" value={filters.status ?? ''} onChange={(event) => filter('status', event.target.value)}>
            <option value="">All statuses</option>
            <option value="sent">Sent</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <div className="overflow-x-auto rounded-lg border border-line">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface-muted text-ink-muted">
              <tr><th className="p-3">Channel</th><th className="p-3">Recipient</th><th className="p-3">Status</th><th className="p-3">Sent</th><th className="p-3">Error</th></tr>
            </thead>
            <tbody>
              {logs.data.map((log) => (
                <tr key={log.id} className="border-t border-line">
                  <td className="p-3"><Badge variant="outline">{log.channel}</Badge></td>
                  <td className="p-3">{log.recipient}</td>
                  <td className="p-3"><Badge variant={log.status === 'sent' ? 'success' : log.status === 'failed' ? 'destructive' : 'warning'}>{log.status}</Badge></td>
                  <td className="p-3">{log.sent_at ? new Date(log.sent_at).toLocaleString() : 'Pending'}</td>
                  <td className="p-3">{log.error ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {logs.data.length === 0 && <p className="text-ink-muted p-6 text-center">No notification logs found.</p>}
        </div>
      </div>
    </AdminLayout>
  );
}

NotificationLogs.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
