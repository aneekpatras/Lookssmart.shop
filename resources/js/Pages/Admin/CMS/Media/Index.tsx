import { Head, router, useForm } from '@inertiajs/react';
import axios, { isAxiosError } from 'axios';
import { Copy, FileIcon, Trash2, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { EmptyState } from '@/Components/ui/empty-state';
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
import { cn } from '@/lib/utils';

interface UsageEntry {
  label: string;
  clearable: boolean;
}

interface MediaAssetRow {
  id: number;
  path: string;
  url: string;
  original_name: string | null;
  mime_type: string | null;
  size: number;
  uploader: string | null;
  created_at: string | null;
  usages: UsageEntry[];
}

interface MediaIndexPageProps {
  assets: {
    data: MediaAssetRow[];
    current_page: number;
    last_page: number;
    total: number;
  };
  filters: {
    search: string | null;
    type: string | null;
    from: string | null;
    to: string | null;
  };
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function UploadDropzone() {
  const [dragging, setDragging] = React.useState(false);
  const inputRef = React.useRef<HTMLInputElement>(null);
  const form = useForm<{ files: File[] }>({ files: [] });

  function upload(files: File[]) {
    if (files.length === 0) return;

    form.setData('files', files);
    form.post('/admin/media', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Files uploaded.');
        form.reset();
      },
    });
  }

  return (
    <Card>
      <CardContent
        className={cn(
          'border-2 border-dashed p-8 text-center transition-colors',
          dragging ? 'border-accent-500 bg-accent-50' : 'border-border-soft',
        )}
        onDragOver={(e) => {
          e.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragging(false);
          upload(Array.from(e.dataTransfer.files));
        }}
      >
        <Upload className="text-accent-600 mx-auto size-8" />
        <p className="mt-3 text-sm">
          Drag and drop images here, or{' '}
          <button
            type="button"
            className="text-accent-700 font-medium underline"
            onClick={() => inputRef.current?.click()}
          >
            browse
          </button>
        </p>
        <Label htmlFor="media-upload" className="sr-only">
          Upload files
        </Label>
        <Input
          id="media-upload"
          ref={inputRef}
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,image/gif"
          className="hidden"
          onChange={(e) => upload(Array.from(e.target.files ?? []))}
        />
        {form.errors.files && <p className="mt-2 text-sm text-red-600">{form.errors.files}</p>}
      </CardContent>
    </Card>
  );
}

function FiltersBar({ filters }: { filters: MediaIndexPageProps['filters'] }) {
  const [search, setSearch] = React.useState(filters.search ?? '');

  function apply(next: Partial<Record<'search' | 'type' | 'from' | 'to', string | null>>) {
    router.get(
      '/admin/media',
      {
        search: next.search !== undefined ? next.search || undefined : filters.search || undefined,
        type: next.type !== undefined ? next.type || undefined : filters.type || undefined,
        from: next.from !== undefined ? next.from || undefined : filters.from || undefined,
        to: next.to !== undefined ? next.to || undefined : filters.to || undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <form
        className="flex items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          apply({ search });
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="media-search">Search</Label>
          <Input
            id="media-search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="File name..."
          />
        </div>
        <Button type="submit" variant="outline">
          Search
        </Button>
      </form>

      <div className="space-y-1.5">
        <Label htmlFor="media-type">Type</Label>
        <Select value={filters.type ?? 'all'} onValueChange={(value) => apply({ type: value === 'all' ? null : value })}>
          <SelectTrigger id="media-type" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            <SelectItem value="images">Images</SelectItem>
            <SelectItem value="documents">Documents</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="media-from">From</Label>
        <Input id="media-from" type="date" value={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="media-to">To</Label>
        <Input id="media-to" type="date" value={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value })} />
      </div>
    </div>
  );
}

function AssetDetailDialog({ asset, onClose }: { asset: MediaAssetRow; onClose: () => void }) {
  const [confirmClear, setConfirmClear] = React.useState(false);
  const [deleting, setDeleting] = React.useState(false);
  const blocking = asset.usages.filter((usage) => !usage.clearable);
  const clearableOnly = asset.usages.length > 0 && blocking.length === 0;

  async function copyUrl() {
    try {
      await navigator.clipboard.writeText(asset.url);
      toast.success('Link copied.');
    } catch {
      toast.error('Could not copy link.');
    }
  }

  async function destroy() {
    setDeleting(true);
    try {
      await axios.delete(`/admin/media/${asset.id}`, {
        params: asset.usages.length > 0 ? { force: true } : undefined,
      });
      toast.success('File deleted.');
      onClose();
      router.reload({ only: ['assets'] });
    } catch (error) {
      const message = isAxiosError(error) ? (error.response?.data?.message ?? 'Could not delete file.') : 'Could not delete file.';
      toast.error(message);
    } finally {
      setDeleting(false);
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{asset.original_name ?? 'File details'}</DialogTitle>
        </DialogHeader>

        <img src={asset.url} alt="" className="max-h-80 w-full rounded-lg object-contain bg-accent-50" />

        <div className="flex items-center gap-2">
          <Input readOnly value={asset.url} aria-label="File URL" />
          <Button type="button" variant="outline" size="icon" aria-label="Copy link" onClick={() => void copyUrl()}>
            <Copy className="size-4" />
          </Button>
        </div>

        <dl className="grid grid-cols-2 gap-2 text-sm">
          <dt className="text-ink-muted">Size</dt>
          <dd>{formatSize(asset.size)}</dd>
          <dt className="text-ink-muted">Type</dt>
          <dd>{asset.mime_type ?? 'Unknown'}</dd>
          <dt className="text-ink-muted">Uploaded by</dt>
          <dd>{asset.uploader ?? 'Unknown'}</dd>
        </dl>

        {asset.usages.length > 0 && (
          <div className="space-y-2">
            <p className="text-sm font-medium">Currently used in:</p>
            <div className="flex flex-wrap gap-2">
              {asset.usages.map((usage) => (
                <Badge key={usage.label} variant={usage.clearable ? 'outline' : 'destructive'}>
                  {usage.label}
                </Badge>
              ))}
            </div>
          </div>
        )}

        {blocking.length > 0 && (
          <p className="text-sm text-red-600">
            This file is required and cannot be deleted while in use above.
          </p>
        )}

        {clearableOnly && (
          <label className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              className="mt-0.5"
              checked={confirmClear}
              onChange={(e) => setConfirmClear(e.target.checked)}
            />
            I understand deleting this file will remove it from the references listed above.
          </label>
        )}

        <DialogFooter>
          <Button
            type="button"
            variant="destructive"
            disabled={blocking.length > 0 || (clearableOnly && !confirmClear) || deleting}
            onClick={() => void destroy()}
          >
            <Trash2 className="size-4" />
            Delete file
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default function MediaIndex({ assets, filters }: MediaIndexPageProps) {
  const [selected, setSelected] = React.useState<MediaAssetRow | null>(null);

  return (
    <>
      <Head title="Media Library" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Media Library</h1>
          <p className="text-ink-muted text-sm">Every image uploaded across the CMS, in one place.</p>
        </div>

        <UploadDropzone />
        <FiltersBar filters={filters} />

        {assets.data.length === 0 ? (
          <EmptyState
            icon={FileIcon}
            title="No files found"
            description="Upload files above or adjust your filters."
          />
        ) : (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {assets.data.map((asset) => (
              <button
                key={asset.id}
                type="button"
                onClick={() => setSelected(asset)}
                className="border-border-soft hover:border-accent-500 overflow-hidden rounded-lg border text-left transition-colors"
              >
                <div className="bg-accent-50 aspect-square">
                  <img src={asset.url} alt="" className="size-full object-cover" loading="lazy" />
                </div>
                <div className="space-y-1 p-2">
                  <p className="truncate text-xs font-medium">{asset.original_name ?? asset.path}</p>
                  <div className="flex items-center justify-between">
                    <Badge variant="outline" className="text-[10px]">
                      {asset.mime_type?.split('/')[1]?.toUpperCase() ?? 'FILE'}
                    </Badge>
                    <span className="text-ink-muted text-[10px]">{formatSize(asset.size)}</span>
                  </div>
                  {asset.usages.length > 0 && (
                    <Badge variant="accent" className="text-[10px]">
                      In use
                    </Badge>
                  )}
                </div>
              </button>
            ))}
          </div>
        )}

        {assets.last_page > 1 && (
          <nav aria-label="Media pagination" className="flex justify-center gap-2">
            {Array.from({ length: assets.last_page }, (_, i) => i + 1).map((page) => (
              <Button
                key={page}
                type="button"
                variant={page === assets.current_page ? 'accent' : 'outline'}
                size="sm"
                onClick={() =>
                  router.get('/admin/media', { ...filters, page }, { preserveState: true, preserveScroll: true })
                }
              >
                {page}
              </Button>
            ))}
          </nav>
        )}
      </div>

      {selected && <AssetDetailDialog asset={selected} onClose={() => setSelected(null)} />}
    </>
  );
}

MediaIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
