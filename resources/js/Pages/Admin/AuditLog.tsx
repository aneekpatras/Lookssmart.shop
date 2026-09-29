import { Head, router } from '@inertiajs/react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
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

interface AuditActivityRow {
  id: number;
  log_name: string | null;
  description: string | null;
  event: string | null;
  subject_type: string | null;
  subject_id: number | null;
  causer_name: string;
  properties: Record<string, unknown>;
  created_at: string | null;
}

interface AuditLogPageProps {
  activities: {
    data: AuditActivityRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: {
    log_name: string | null;
    causer_id: number | null;
    from: string | null;
    to: string | null;
  };
  logNames: string[];
  causers: { id: number; name: string }[];
}

const EVENT_VARIANT: Record<string, 'default' | 'accent' | 'success' | 'warning' | 'destructive'> =
  {
    created: 'success',
    updated: 'accent',
    deleted: 'destructive',
  };

const columns: DataTableColumn<AuditActivityRow>[] = [
  {
    id: 'created_at',
    header: 'When',
    cell: (row) => (row.created_at ? new Date(row.created_at).toLocaleString() : '—'),
    alwaysVisible: true,
  },
  { id: 'causer_name', header: 'Actor', cell: (row) => row.causer_name },
  {
    id: 'log_name',
    header: 'Log',
    cell: (row) => (row.log_name ? <Badge variant="outline">{row.log_name}</Badge> : '—'),
  },
  {
    id: 'event',
    header: 'Event',
    cell: (row) =>
      row.event ? (
        <Badge variant={EVENT_VARIANT[row.event] ?? 'default'}>{row.event}</Badge>
      ) : (
        row.description
      ),
  },
  {
    id: 'subject',
    header: 'Subject',
    cell: (row) => (row.subject_type ? `${row.subject_type} #${row.subject_id}` : '—'),
  },
  {
    id: 'properties',
    header: 'Details',
    cell: (row) => (
      <code
        className="text-ink-muted block max-w-md truncate text-xs"
        title={JSON.stringify(row.properties)}
      >
        {JSON.stringify(row.properties)}
      </code>
    ),
  },
];

export default function AuditLog({ activities, filters, logNames, causers }: AuditLogPageProps) {
  function updateFilter(key: string, value: string) {
    router.get(
      '/admin/audit-log',
      { ...filters, [key]: value || undefined, page: undefined },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <>
      <Head title="Audit Log" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Audit Log</h1>
          <p className="text-ink-muted text-sm">
            Every recorded change to bookings, payments, users, and settings — super-admin only.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <div className="w-48 space-y-1.5">
            <Label htmlFor="filter-log">Log</Label>
            <Select
              value={filters.log_name ?? 'all'}
              onValueChange={(value) => updateFilter('log_name', value === 'all' ? '' : value)}
            >
              <SelectTrigger id="filter-log">
                <SelectValue placeholder="All logs" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All logs</SelectItem>
                {logNames.map((name) => (
                  <SelectItem key={name} value={name}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="w-48 space-y-1.5">
            <Label htmlFor="filter-causer">Actor</Label>
            <Select
              value={filters.causer_id ? String(filters.causer_id) : 'all'}
              onValueChange={(value) => updateFilter('causer_id', value === 'all' ? '' : value)}
            >
              <SelectTrigger id="filter-causer">
                <SelectValue placeholder="Anyone" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Anyone</SelectItem>
                {causers.map((causer) => (
                  <SelectItem key={causer.id} value={String(causer.id)}>
                    {causer.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="filter-from">From</Label>
            <Input
              id="filter-from"
              type="date"
              defaultValue={filters.from ?? ''}
              onChange={(e) => updateFilter('from', e.target.value)}
              className="w-40"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="filter-to">To</Label>
            <Input
              id="filter-to"
              type="date"
              defaultValue={filters.to ?? ''}
              onChange={(e) => updateFilter('to', e.target.value)}
              className="w-40"
            />
          </div>
        </div>

        <DataTable<AuditActivityRow>
          columns={columns}
          rows={activities.data}
          meta={activities satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          showSearch={false}
          emptyTitle="No activity recorded"
          csvFilename="audit-log.csv"
        />
      </div>
    </>
  );
}

AuditLog.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
