import { Head, Link, router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

import {
  DataTable,
  type DataTableColumn,
  type DataTablePaginationMeta,
} from '@/Components/admin/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import AdminLayout from '@/Layouts/AdminLayout';

interface PostRow {
  id: number;
  title: string;
  slug: string;
  status: 'draft' | 'scheduled' | 'published';
  category: string | null;
  author: string | null;
  published_at: string | null;
  scheduled_at: string | null;
}

interface CategoryOption {
  id: number;
  name: string;
  slug: string;
}

interface BlogIndexPageProps {
  posts: {
    data: PostRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  categories: CategoryOption[];
  filters: { search: string | null; sort: string; direction: 'asc' | 'desc' };
}

const STATUS_VARIANT: Record<PostRow['status'], 'accent' | 'outline' | 'warning'> = {
  published: 'accent',
  scheduled: 'warning',
  draft: 'outline',
};

function StatusBadge({ post }: { post: PostRow }) {
  const label = post.status === 'scheduled' ? 'Scheduled' : post.status === 'published' ? 'Published' : 'Draft';

  return (
    <div className="flex flex-col gap-0.5">
      <Badge variant={STATUS_VARIANT[post.status]}>{label}</Badge>
      {post.status === 'scheduled' && post.scheduled_at && (
        <span className="text-ink-muted text-xs">{new Date(post.scheduled_at).toLocaleString()}</span>
      )}
      {post.status === 'published' && post.published_at && (
        <span className="text-ink-muted text-xs">{new Date(post.published_at).toLocaleDateString()}</span>
      )}
    </div>
  );
}

function PostRowActions({ row }: { row: PostRow }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon" aria-label={`Actions for ${row.title}`}>
          <MoreHorizontal className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <Link href={`/admin/blog/${row.id}/edit`}>Edit</Link>
        </DropdownMenuItem>
        <DropdownMenuItem
          className="text-red-600 hover:bg-red-50 focus:bg-red-50"
          onSelect={() => {
            if (confirm(`Delete "${row.title}"?`)) {
              router.delete(`/admin/blog/${row.id}`);
            }
          }}
        >
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function CategoriesPanel({ categories }: { categories: CategoryOption[] }) {
  const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    post('/admin/blog-categories', { onSuccess: () => reset() });
  }

  function destroy(category: CategoryOption) {
    if (confirm(`Delete the "${category.name}" category? Posts in it are not deleted.`)) {
      router.delete(`/admin/blog-categories/${category.id}`);
    }
  }

  return (
    <Card>
      <CardContent className="space-y-4 p-5">
        <h2 className="font-display text-ink text-lg font-medium">Categories</h2>
        <form onSubmit={submit} className="flex items-start gap-2">
          <div className="flex-1">
            <Input
              aria-label="New category name"
              placeholder="New category name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
            />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
          </div>
          <Button type="submit" disabled={processing}>
            <Plus className="size-4" />
            Add
          </Button>
        </form>
        {categories.length > 0 && (
          <ul className="divide-border-soft divide-y">
            {categories.map((category) => (
              <li key={category.id} className="flex items-center justify-between py-2 text-sm">
                {category.name}
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={`Delete ${category.name}`}
                  onClick={() => destroy(category)}
                >
                  <Trash2 className="size-4 text-red-600" />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

export default function BlogIndex({ posts, categories, filters }: BlogIndexPageProps) {
  const columns: DataTableColumn<PostRow>[] = [
    {
      id: 'title',
      header: 'Title',
      sortable: true,
      alwaysVisible: true,
      cell: (row) => <span className="font-medium">{row.title}</span>,
    },
    { id: 'category', header: 'Category', cell: (row) => row.category ?? '—' },
    { id: 'author', header: 'Author', cell: (row) => row.author ?? '—' },
    {
      id: 'status',
      header: 'Status',
      sortable: true,
      cell: (row) => <StatusBadge post={row} />,
    },
    {
      id: 'actions',
      header: '',
      alwaysVisible: true,
      className: 'w-10 text-right',
      cell: (row) => <PostRowActions row={row} />,
    },
  ];

  return (
    <>
      <Head title="Blog" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Blog</h1>
            <p className="text-ink-muted text-sm">Articles, categories, and publishing schedule.</p>
          </div>
          <Button asChild>
            <Link href="/admin/blog/create">
              <Plus className="size-4" />
              New post
            </Link>
          </Button>
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
          <DataTable<PostRow>
            columns={columns}
            rows={posts.data}
            meta={posts satisfies DataTablePaginationMeta}
            getRowId={(row) => row.id}
            filters={filters}
            searchPlaceholder="Search posts..."
            emptyTitle="No posts yet"
            csvFilename="posts.csv"
          />
          <CategoriesPanel categories={categories} />
        </div>
      </div>
    </>
  );
}

BlogIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
