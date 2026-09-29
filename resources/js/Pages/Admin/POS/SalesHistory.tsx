import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
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

interface SaleRow {
  id: number;
  sale_number: string;
  customer_name: string | null;
  cashier_name: string | null;
  total: string;
  status: string;
  created_at: string | null;
}

interface Filters {
  invoice: string | null;
  from: string | null;
  to: string | null;
  customer_id: number | null;
  cashier_id: number | null;
  status: string | null;
  [key: string]: unknown;
}

interface SalesHistoryPageProps {
  sales: {
    data: SaleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: Filters;
  customers: { id: number; name: string }[];
  cashiers: { id: number; name: string }[];
}

const STATUS_VARIANT: Record<string, 'success' | 'accent' | 'destructive'> = {
  completed: 'success',
  refunded: 'destructive',
  voided: 'destructive',
};

const ALL = '__all__';

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function buildQuery(filters: Filters): string {
  const params = new URLSearchParams();
  if (filters.invoice) params.set('invoice', filters.invoice);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.customer_id) params.set('customer_id', String(filters.customer_id));
  if (filters.cashier_id) params.set('cashier_id', String(filters.cashier_id));
  if (filters.status) params.set('status', filters.status);
  return params.toString();
}

function FilterBar({
  filters,
  customers,
  cashiers,
}: {
  filters: Filters;
  customers: { id: number; name: string }[];
  cashiers: { id: number; name: string }[];
}) {
  const [invoice, setInvoice] = React.useState(filters.invoice ?? '');
  const [from, setFrom] = React.useState(filters.from ?? '');
  const [to, setTo] = React.useState(filters.to ?? '');

  function apply(overrides: Partial<Filters> = {}) {
    const next: Filters = { ...filters, invoice, from, to, ...overrides };
    router.get('/admin/pos/sales-history', {
      invoice: next.invoice || undefined,
      from: next.from || undefined,
      to: next.to || undefined,
      customer_id: next.customer_id ?? undefined,
      cashier_id: next.cashier_id ?? undefined,
      status: next.status ?? undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function quickRange(range: 'today' | 'week' | 'month') {
    const now = new Date();
    let start = todayIso();

    if (range === 'week') {
      const weekAgo = new Date(now);
      weekAgo.setDate(now.getDate() - 7);
      start = weekAgo.toISOString().slice(0, 10);
    } else if (range === 'month') {
      const monthAgo = new Date(now);
      monthAgo.setMonth(now.getMonth() - 1);
      start = monthAgo.toISOString().slice(0, 10);
    }

    setFrom(start);
    setTo(todayIso());
    apply({ from: start, to: todayIso() });
  }

  function reset() {
    setInvoice('');
    setFrom('');
    setTo('');
    router.get('/admin/pos/sales-history', {}, { preserveState: true, preserveScroll: true, replace: true });
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="sh-invoice">Invoice #</Label>
          <Input
            id="sh-invoice"
            value={invoice}
            onChange={(e) => setInvoice(e.target.value)}
            onBlur={() => apply()}
            placeholder="INV-000001"
            className="w-40"
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sh-from">From</Label>
          <Input id="sh-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} onBlur={() => apply()} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sh-to">To</Label>
          <Input id="sh-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} onBlur={() => apply()} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sh-customer">Customer</Label>
          <Select
            value={filters.customer_id ? String(filters.customer_id) : ALL}
            onValueChange={(value) => apply({ customer_id: value === ALL ? null : Number(value) })}
          >
            <SelectTrigger id="sh-customer" className="w-40">
              <SelectValue placeholder="All" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All</SelectItem>
              {customers.map((customer) => (
                <SelectItem key={customer.id} value={String(customer.id)}>
                  {customer.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sh-cashier">Cashier</Label>
          <Select
            value={filters.cashier_id ? String(filters.cashier_id) : ALL}
            onValueChange={(value) => apply({ cashier_id: value === ALL ? null : Number(value) })}
          >
            <SelectTrigger id="sh-cashier" className="w-40">
              <SelectValue placeholder="All" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All</SelectItem>
              {cashiers.map((cashier) => (
                <SelectItem key={cashier.id} value={String(cashier.id)}>
                  {cashier.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sh-status">Status</Label>
          <Select value={filters.status ?? ALL} onValueChange={(value) => apply({ status: value === ALL ? null : value })}>
            <SelectTrigger id="sh-status" className="w-36">
              <SelectValue placeholder="All" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="voided">Voided</SelectItem>
              <SelectItem value="refunded">Refunded</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button type="button" variant="outline" onClick={reset}>
          Reset
        </Button>
        <Button type="button" variant="outline" asChild className="ml-auto">
          <a href={`/admin/pos/sales-history/export?${buildQuery(filters)}`}>
            <Download className="size-4" />
            Export CSV
          </a>
        </Button>
      </div>

      <div className="flex gap-2">
        <Button type="button" size="sm" variant="ghost" onClick={() => quickRange('today')}>
          Today
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={() => quickRange('week')}>
          Weekly
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={() => quickRange('month')}>
          Monthly
        </Button>
      </div>
    </div>
  );
}

export default function SalesHistory({ sales, filters, customers, cashiers }: SalesHistoryPageProps) {
  const columns: DataTableColumn<SaleRow>[] = [
    { id: 'sale_number', header: 'Invoice #', alwaysVisible: true, cell: (row) => row.sale_number },
    {
      id: 'created_at',
      header: 'Date',
      cell: (row) => (row.created_at ? new Date(row.created_at).toLocaleString() : '—'),
    },
    { id: 'customer_name', header: 'Customer', cell: (row) => row.customer_name ?? 'Walk-in Customer' },
    { id: 'cashier_name', header: 'Cashier', cell: (row) => row.cashier_name ?? '—' },
    { id: 'total', header: 'Total', cell: (row) => formatCurrency(Number(row.total)) },
    {
      id: 'status',
      header: 'Status',
      cell: (row) => (
        <Badge variant={STATUS_VARIANT[row.status] ?? 'default'} className="capitalize">
          {row.status}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-16 text-right',
      cell: (row) => (
        <Link href={`/admin/pos/sales/${row.id}`} className="text-accent-600 text-sm hover:underline">
          View
        </Link>
      ),
    },
  ];

  return (
    <>
      <Head title="Sales History" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Sales History</h1>
          <p className="text-ink-muted text-sm">Every finalized sale, voided or not.</p>
        </div>

        <FilterBar filters={filters} customers={customers} cashiers={cashiers} />

        <DataTable<SaleRow>
          columns={columns}
          rows={sales.data}
          meta={sales satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          showSearch={false}
          emptyTitle="No sales match these filters"
        />
      </div>
    </>
  );
}

SalesHistory.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
