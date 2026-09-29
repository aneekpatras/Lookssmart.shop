import { Head, router } from '@inertiajs/react';
import { Download, MoreHorizontal, Plus } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { ServiceFormDialog, type ServiceDialogRow } from '@/Components/admin/ServiceFormDialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface ServiceRow extends ServiceDialogRow {
  slug: string;
  category: string | null;
}

interface ServicesPageProps {
  services: {
    data: ServiceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: { search: string | null; category_id: number | null; sort: string; direction: 'asc' | 'desc' };
  categories: { id: number; name: string }[];
  staff: { id: number; name: string | null }[];
}

function ServiceRowActions({ row, onEdit }: { row: ServiceRow; onEdit: (row: ServiceRow) => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon" aria-label={`Actions for ${row.name}`}>
          <MoreHorizontal className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onSelect={() => onEdit(row)}>Edit</DropdownMenuItem>
        <DropdownMenuItem
          className="text-red-600 hover:bg-red-50 focus:bg-red-50"
          onSelect={() => {
            if (confirm(`Delete "${row.name}"?`)) {
              router.delete(`/admin/services/${row.id}`);
            }
          }}
        >
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default function Services({ services, filters, categories, staff }: ServicesPageProps) {
  const [dialogTarget, setDialogTarget] = React.useState<ServiceRow | null | undefined>(undefined);

  const columns: DataTableColumn<ServiceRow>[] = [
    {
      id: 'name',
      header: 'Name',
      sortable: true,
      alwaysVisible: true,
      cell: (row) => (
        <div className="flex items-center gap-3">
          {row.image_url ? (
            <img src={row.image_url} alt="" className="size-8 rounded object-cover" />
          ) : (
            <div className="bg-sand size-8 rounded" />
          )}
          <div>
            <p>{row.name}</p>
            {row.sku ? <p className="text-ink-muted text-xs">{row.sku}</p> : null}
          </div>
        </div>
      ),
    },
    { id: 'category', header: 'Category', cell: (row) => row.category ?? '—' },
    {
      id: 'duration_min',
      header: 'Duration',
      sortable: true,
      cell: (row) => `${row.duration_min} min`,
    },
    {
      id: 'base_price',
      header: 'Price',
      sortable: true,
      cell: (row) => formatCurrency(row.base_price),
    },
    {
      id: 'is_active',
      header: 'Status',
      cell: (row) => (
        <div className="flex gap-1">
          <Badge variant={row.is_active ? 'accent' : 'outline'}>
            {row.is_active ? 'Active' : 'Inactive'}
          </Badge>
          {row.is_featured && <Badge variant="outline">Featured</Badge>}
        </div>
      ),
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-10 text-right',
      cell: (row) => <ServiceRowActions row={row} onEdit={setDialogTarget} />,
    },
  ];

  return (
    <>
      <Head title="Services" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Services</h1>
            <p className="text-ink-muted text-sm">Every bookable service and its pricing.</p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" asChild>
              <a href="/admin/services/export">
                <Download className="size-4" />
                Export
              </a>
            </Button>
            <Button onClick={() => setDialogTarget(null)}>
              <Plus className="size-4" />
              New service
            </Button>
          </div>
        </div>
        <DataTable<ServiceRow>
          columns={columns}
          rows={services.data}
          meta={services satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          searchPlaceholder="Search services..."
          emptyTitle="No services yet"
          csvFilename="services.csv"
        />
      </div>

      {dialogTarget !== undefined && (
        <ServiceFormDialog
          key={dialogTarget?.id ?? 'new'}
          open={dialogTarget !== undefined}
          onOpenChange={(open) => {
            if (!open) {
              setDialogTarget(undefined);
            }
          }}
          service={dialogTarget}
          categories={categories}
          staff={staff}
        />
      )}
    </>
  );
}

Services.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
