import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Images, Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import AdminLayout from '@/Layouts/AdminLayout';

interface AlbumRow {
  id: number;
  title: string;
  slug: string;
  category_name: string | null;
  images_count: number;
  cover_image_url: string | null;
  is_active: boolean;
}

interface CategoryRow {
  id: number;
  name: string;
  slug: string;
  subtitle: string | null;
  sort: number;
  galleries_count: number;
}

interface GalleryIndexPageProps {
  galleries: AlbumRow[];
  categories: CategoryRow[];
}

function CategoryDialog({
  category,
  trigger,
}: {
  category?: CategoryRow;
  trigger: React.ReactNode;
}) {
  const [open, setOpen] = React.useState(false);
  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: category?.name ?? '',
    subtitle: category?.subtitle ?? '',
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const onSuccess = () => {
      setOpen(false);
      reset();
    };
    if (category) {
      put(`/admin/gallery-categories/${category.id}`, { onSuccess });
    } else {
      post('/admin/gallery-categories', { onSuccess });
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{category ? 'Edit category' : 'New category'}</DialogTitle>
            <DialogDescription>
              Categories group gallery albums into the stacked sections shown on the public Gallery
              page, in the order set below.
            </DialogDescription>
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
            {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="category-subtitle">Subtitle</Label>
            <Input
              id="category-subtitle"
              placeholder="Shown under the section title on the public page"
              value={data.subtitle}
              onChange={(e) => setData('subtitle', e.target.value)}
              aria-invalid={!!errors.subtitle}
            />
            {errors.subtitle && <p className="text-sm text-red-600">{errors.subtitle}</p>}
          </div>
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

function CategoriesCard({ categories }: { categories: CategoryRow[] }) {
  function destroy(category: CategoryRow) {
    if (
      confirm(
        `Delete the "${category.name}" category? Albums in it are not deleted — they just become uncategorized.`,
      )
    ) {
      router.delete(`/admin/gallery-categories/${category.id}`, { preserveScroll: true });
    }
  }

  function move(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= categories.length) return;

    const reordered = [...categories];
    const current = reordered[index];
    const swapWith = reordered[target];
    if (!current || !swapWith) return;
    reordered[index] = swapWith;
    reordered[target] = current;

    router.post(
      '/admin/gallery-categories/reorder',
      { order: reordered.map((category) => category.id) },
      { preserveScroll: true },
    );
  }

  return (
    <Card>
      <CardContent className="space-y-4 p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="font-display text-ink text-lg font-medium">Categories</h2>
            <p className="text-ink-muted text-sm">
              Controls the order category sections appear in on the public Gallery page.
            </p>
          </div>
          <CategoryDialog
            trigger={
              <Button type="button" variant="outline" size="sm">
                <Plus className="size-4" />
                New category
              </Button>
            }
          />
        </div>

        {categories.length === 0 ? (
          <p className="text-ink-muted text-sm">
            No categories yet. Create one, then assign albums to it below.
          </p>
        ) : (
          <div className="divide-border-soft divide-y">
            {categories.map((category, index) => (
              <div key={category.id} className="flex items-center justify-between gap-4 py-3">
                <div className="min-w-0">
                  <p className="text-ink truncate font-medium">{category.name}</p>
                  <p className="text-ink-muted truncate text-xs">
                    {category.subtitle || 'No subtitle'} &middot; {category.galleries_count}{' '}
                    {category.galleries_count === 1 ? 'album' : 'albums'}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    disabled={index === 0}
                    aria-label={`Move "${category.name}" up`}
                    onClick={() => move(index, -1)}
                  >
                    <ArrowUp className="size-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    disabled={index === categories.length - 1}
                    aria-label={`Move "${category.name}" down`}
                    onClick={() => move(index, 1)}
                  >
                    <ArrowDown className="size-4" />
                  </Button>
                  <CategoryDialog
                    category={category}
                    trigger={
                      <Button type="button" variant="ghost" size="sm">
                        Edit
                      </Button>
                    }
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Delete "${category.name}"`}
                    onClick={() => destroy(category)}
                  >
                    <Trash2 className="size-4 text-red-600" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default function GalleryIndex({ galleries, categories }: GalleryIndexPageProps) {
  function toggleActive(album: AlbumRow) {
    router.put(`/admin/gallery/${album.id}`, { title: album.title, is_active: !album.is_active }, { preserveScroll: true });
  }

  function destroy(album: AlbumRow) {
    if (confirm(`Delete the "${album.title}" album and all of its images?`)) {
      router.delete(`/admin/gallery/${album.id}`);
    }
  }

  return (
    <>
      <Head title="Gallery" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Gallery</h1>
            <p className="text-ink-muted text-sm">Albums, photos, and before/after transformations.</p>
          </div>
          <Button asChild>
            <Link href="/admin/gallery/create">
              <Plus className="size-4" />
              New album
            </Link>
          </Button>
        </div>

        <CategoriesCard categories={categories} />

        {galleries.length === 0 ? (
          <EmptyState
            icon={Images}
            title="No albums yet"
            description="Create your first gallery album to start uploading photos."
          />
        ) : (
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {galleries.map((album) => (
              <Card key={album.id} className="overflow-hidden">
                <Link href={`/admin/gallery/${album.id}/edit`} className="block aspect-[4/3] bg-accent-50">
                  {album.cover_image_url ? (
                    <img src={album.cover_image_url} alt="" className="size-full object-cover" />
                  ) : (
                    <div className="text-accent-600 flex size-full items-center justify-center">
                      <Images className="size-8" />
                    </div>
                  )}
                </Link>
                <CardContent className="space-y-3 p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <Link href={`/admin/gallery/${album.id}/edit`} className="font-medium hover:underline">
                        {album.title}
                      </Link>
                      {album.category_name && (
                        <div className="mt-1">
                          <Badge variant="outline">{album.category_name}</Badge>
                        </div>
                      )}
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={`Delete ${album.title}`}
                      onClick={() => destroy(album)}
                    >
                      <Trash2 className="size-4 text-red-600" />
                    </Button>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-ink-muted">
                      {album.images_count} {album.images_count === 1 ? 'image' : 'images'}
                    </span>
                    <label className="flex items-center gap-2">
                      <Switch
                        checked={album.is_active}
                        onCheckedChange={() => toggleActive(album)}
                        aria-label={`Toggle "${album.title}" active`}
                      />
                      <span className="text-ink-muted text-xs">
                        {album.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </label>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

GalleryIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
