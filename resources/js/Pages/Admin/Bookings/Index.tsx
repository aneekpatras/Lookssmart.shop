import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
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

interface BookingRow {
  id: number;
  code: string;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  customer_name: string | null;
  services: string[];
  duration_minutes: number | null;
  total: string;
  source: string | null;
}

interface BookingsIndexPageProps {
  bookings: {
    data: BookingRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  statuses: string[];
  filters: {
    search: string | null;
    status: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: 'asc' | 'desc';
  };
}

function formatDuration(minutes: number | null | undefined): string {
  if (minutes == null || Number.isNaN(minutes) || minutes <= 0) return '—';

  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;

  if (hours === 0) return `${mins} Min${mins === 1 ? '' : 's'}`;
  if (mins === 0) return `${hours} Hour${hours === 1 ? '' : 's'}`;
  return `${hours} Hour${hours === 1 ? '' : 's'} ${mins} Min${mins === 1 ? '' : 's'}`;
}

const STATUS_VARIANT: Record<string, 'outline' | 'accent' | 'destructive' | 'success'> = {
  pending: 'outline',
  confirmed: 'accent',
  checked_in: 'accent',
  completed: 'success',
  cancelled: 'destructive',
  no_show: 'destructive',
};

function BookingRowActions({ row }: { row: BookingRow }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon" aria-label={`Actions for ${row.code}`}>
          <MoreHorizontal className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <Link href={`/admin/bookings/${row.id}`}>View</Link>
        </DropdownMenuItem>
        <DropdownMenuItem
          className="text-red-600 hover:bg-red-50 focus:bg-red-50"
          onSelect={() => {
            if (confirm(`Delete booking "${row.code}"? This cannot be undone from this screen.`)) {
              router.delete(`/admin/bookings/${row.id}`);
            }
          }}
        >
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default function BookingsIndex({ bookings, statuses, filters }: BookingsIndexPageProps) {
  function reload(params: Record<string, string | number | undefined>) {
    router.get(
      '/admin/bookings',
      { ...filters, ...params },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  const columns: DataTableColumn<BookingRow>[] = [
    {
      id: 'code',
      header: 'Booking',
      alwaysVisible: true,
      cell: (row) => (
        <Link href={`/admin/bookings/${row.id}`} className="font-medium hover:underline">
          {row.code}
        </Link>
      ),
    },
    {
      id: 'customer_name',
      header: 'Customer',
      cell: (row) => row.customer_name ?? '—',
    },
    {
      id: 'services',
      header: 'Services',
      cell: (row) => row.services?.join(', ') || '—',
    },
    {
      id: 'duration_minutes',
      header: 'Total Duration',
      cell: (row) => formatDuration(row.duration_minutes),
    },
    {
      id: 'starts_at',
      header: 'When',
      sortable: true,
      cell: (row) => (row.starts_at ? new Date(row.starts_at).toLocaleString() : '—'),
    },
    {
      id: 'total',
      header: 'Total',
      cell: (row) => row.total,
    },
    {
      id: 'status',
      header: 'Status',
      cell: (row) => (
        <Badge variant={STATUS_VARIANT[row.status] ?? 'outline'} className="capitalize">
          {row.status.replace('_', ' ')}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-10',
      cell: (row) => <BookingRowActions row={row} />,
    },
  ];

  return (
    <>
      <Head title="Bookings" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Bookings</h1>
          <p className="text-ink-muted text-sm">Every appointment booked through the site or by staff.</p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-1.5">
            <Label>Status</Label>
            <Select
              value={filters.status ?? 'all'}
              onValueChange={(value) => reload({ status: value === 'all' ? undefined : value, page: undefined })}
            >
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                {statuses.map((status) => (
                  <SelectItem key={status} value={status} className="capitalize">
                    {status.replace('_', ' ')}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="bookings-from">From</Label>
            <Input
              id="bookings-from"
              type="date"
              value={filters.from ?? ''}
              onChange={(event) => reload({ from: event.target.value || undefined, page: undefined })}
              className="w-40"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="bookings-to">To</Label>
            <Input
              id="bookings-to"
              type="date"
              value={filters.to ?? ''}
              onChange={(event) => reload({ to: event.target.value || undefined, page: undefined })}
              className="w-40"
            />
          </div>
        </div>

        <DataTable<BookingRow>
          columns={columns}
          rows={bookings.data}
          meta={bookings satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          searchPlaceholder="Search code or customer..."
          emptyTitle="No bookings match these filters"
          csvFilename="bookings.csv"
        />
      </div>
    </>
  );
}

BookingsIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
