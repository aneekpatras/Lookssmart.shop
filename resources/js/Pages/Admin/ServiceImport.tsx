import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AdminLayout from '@/Layouts/AdminLayout';

interface ImportRow {
  id: number;
  original_filename: string;
  status: string;
  total_rows: number;
  create_count: number;
  update_count: number;
  error_count: number;
  created_by: string | null;
  created_at: string | null;
}

interface ServiceImportPageProps {
  imports: {
    data: ImportRow[];
  };
}

function statusVariant(status: string) {
  if (status === 'committed') {
    return 'accent' as const;
  }
  if (status === 'failed') {
    return 'destructive' as const;
  }
  return 'outline' as const;
}

export default function ServiceImportPage({ imports }: ServiceImportPageProps) {
  const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({
    file: null,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/admin/services-import', { forceFormData: true });
  }

  return (
    <>
      <Head title="Import Services" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Import Services</h1>
          <p className="text-ink-muted text-sm">
            Bulk create or update services from a spreadsheet. Nothing is saved until you review a
            preview and confirm.
          </p>
        </div>

        <Card className="space-y-4 p-4">
          <div className="flex items-center justify-between gap-4">
            <p className="text-ink text-sm font-medium">1. Download the template</p>
            <Button type="button" variant="outline" size="sm" asChild>
              <a href="/admin/services-import/template">
                <Download className="size-4" />
                Download .xlsx template
              </a>
            </Button>
          </div>
          <form onSubmit={submit} className="space-y-3">
            <p className="text-ink text-sm font-medium">2. Upload your filled-in file</p>
            <div className="flex items-end gap-3">
              <div className="flex-1 space-y-1.5">
                <Label htmlFor="import-file">Spreadsheet (.xlsx, max 5MB, 2,000 rows)</Label>
                <Input
                  id="import-file"
                  type="file"
                  accept=".xlsx"
                  onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                />
                {errors.file ? <p className="text-sm text-red-600">{errors.file}</p> : null}
              </div>
              <Button type="submit" disabled={processing || !data.file}>
                <Upload className="size-4" />
                Upload
              </Button>
            </div>
          </form>
        </Card>

        <div>
          <p className="text-ink mb-2 text-sm font-medium">Import history</p>
          {imports.data.length === 0 ? (
            <EmptyState title="No imports yet" description="Upload a file above to get started." />
          ) : (
            <div className="space-y-2">
              {imports.data.map((item) => (
                <Link
                  key={item.id}
                  href={`/admin/services-import/${item.id}`}
                  className="hover:bg-sand/40 flex items-center justify-between gap-4 rounded-lg border p-3 text-sm"
                >
                  <div>
                    <p className="text-ink">{item.original_filename}</p>
                    <p className="text-ink-muted text-xs">
                      {item.created_by ?? 'Unknown'} ·{' '}
                      {item.created_at ? new Date(item.created_at).toLocaleString() : '—'}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    {item.status === 'previewed' || item.status === 'committed' ? (
                      <span className="text-ink-muted">
                        {item.create_count} create · {item.update_count} update ·{' '}
                        {item.error_count} error
                      </span>
                    ) : null}
                    <Badge variant={statusVariant(item.status)}>{item.status}</Badge>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

ServiceImportPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
