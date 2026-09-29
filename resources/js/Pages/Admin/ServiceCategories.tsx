import { Head, router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Plus } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AdminLayout from '@/Layouts/AdminLayout';

interface CategoryRow {
  id: number;
  name: string;
  slug: string;
  sort: number;
  is_active: boolean;
  services_count: number;
  image_url: string | null;
}

interface ServiceCategoriesPageProps {
  categories: {
    data: CategoryRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  filters: { search: string | null; sort: string; direction: 'asc' | 'desc' };
}

function CategoryDialog({
  category,
  trigger,
}: {
  category?: CategoryRow;
  trigger: React.ReactNode;
}) {
  const [open, setOpen] = React.useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    name: category?.name ?? '',
    is_active: category?.is_active ?? true,
    image: null as File | null,
    _method: category ? 'put' : 'post',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const url = category ? `/admin/service-categories/${category.id}` : '/admin/service-categories';
    post(url, {
      forceFormData: true,
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{category ? 'Edit category' : 'New category'}</DialogTitle>
            <DialogDescription>Categories group services on the booking page.</DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="category-name">Name</Label>
            <Input
              id="category-name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              aria-invalid={!!errors.name}
              required
            />
            {errors.name ? <p className="text-sm text-red-600">{errors.name}</p> : null}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="category-image">Image</Label>
            <Input
              id="category-image"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
            />
            {errors.image ? <p className="text-sm text-red-600">{errors.image}</p> : null}
          </div>
          <label htmlFor="category-active" className="flex items-center gap-2 text-sm">
            <Checkbox
              id="category-active"
              checked={data.is_active}
              onCheckedChange={(checked) => setData('is_active', checked === true)}
            />
            Active
          </label>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              {category ? 'Save changes' : 'Create category'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CategoryRowActions({ row }: { row: CategoryRow }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon" aria-label={`Actions for ${row.name}`}>
          <MoreHorizontal className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <CategoryDialog
          category={row}
          trigger={<DropdownMenuItem onSelect={(e) => e.preventDefault()}>Edit</DropdownMenuItem>}
        />
        <DropdownMenuItem
          className="text-red-600 hover:bg-red-50 focus:bg-red-50"
          onSelect={() => {
            if (confirm(`Delete "${row.name}"? Services in it are not deleted.`)) {
              router.delete(`/admin/service-categories/${row.id}`);
            }
          }}
        >
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default function ServiceCategories({ categories, filters }: ServiceCategoriesPageProps) {
  const columns: DataTableColumn<CategoryRow>[] = [
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
          {row.name}
        </div>
      ),
    },
    { id: 'slug', header: 'Slug', cell: (row) => row.slug },
    { id: 'services_count', header: 'Services', cell: (row) => row.services_count },
    {
      id: 'is_active',
      header: 'Status',
      cell: (row) => (
        <Badge variant={row.is_active ? 'accent' : 'outline'}>
          {row.is_active ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-10 text-right',
      cell: (row) => <CategoryRowActions row={row} />,
    },
  ];

  return (
    <>
      <Head title="Service Categories" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Service Categories</h1>
            <p className="text-ink-muted text-sm">Groups that organize services on the site.</p>
          </div>
          <CategoryDialog
            trigger={
              <Button type="button">
                <Plus className="size-4" />
                New category
              </Button>
            }
          />
        </div>
        <DataTable<CategoryRow>
          columns={columns}
          rows={categories.data}
          meta={categories satisfies DataTablePaginationMeta}
          getRowId={(row) => row.id}
          filters={filters}
          searchPlaceholder="Search categories..."
          emptyTitle="No categories yet"
          csvFilename="service-categories.csv"
        />
      </div>
    </>
  );
}

ServiceCategories.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
