import { Head, router } from '@inertiajs/react';
import { Download, Loader2 } from 'lucide-react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AdminLayout from '@/Layouts/AdminLayout';

interface ImportRowData {
  id: number;
  row_number: number;
  action: 'create' | 'update' | 'error';
  data: Record<string, unknown>;
  errors: string[] | null;
}

interface ServiceImportShowPageProps {
  import: {
    id: number;
    original_filename: string;
    status: string;
    total_rows: number;
    create_count: number;
    update_count: number;
    error_count: number;
    failure_reason: string | null;
    committed_at: string | null;
  };
  rows: {
    data: ImportRowData[];
  };
}

function actionBadge(action: ImportRowData['action']) {
  if (action === 'create') {
    return <Badge variant="accent">Create</Badge>;
  }
  if (action === 'update') {
    return <Badge variant="outline">Update</Badge>;
  }
  return <Badge variant="destructive">Error</Badge>;
}

export default function ServiceImportShow({ import: serviceImport, rows }: ServiceImportShowPageProps) {
  const isProcessing = serviceImport.status === 'queued' || serviceImport.status === 'processing';

  React.useEffect(() => {
    if (!isProcessing) {
      return;
    }

    const interval = setInterval(() => {
      router.reload({ only: ['import', 'rows'] });
    }, 2000);

    return () => clearInterval(interval);
  }, [isProcessing]);

  function commit() {
    if (confirm(`Commit this import? ${serviceImport.create_count} will be created, ${serviceImport.update_count} updated.`)) {
      router.post(`/admin/services-import/${serviceImport.id}/commit`);
    }
  }

  return (
    <>
      <Head title={`Import: ${serviceImport.original_filename}`} />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">{serviceImport.original_filename}</h1>
            <p className="text-ink-muted text-sm">Status: {serviceImport.status}</p>
          </div>
          <div className="flex gap-2">
            {serviceImport.error_count > 0 && (
              <Button type="button" variant="outline" size="sm" asChild>
                <a href={`/admin/services-import/${serviceImport.id}/error-report`}>
                  <Download className="size-4" />
                  Download error report
                </a>
              </Button>
            )}
            {serviceImport.status === 'previewed' && (
              <Button type="button" onClick={commit}>
                Commit import
              </Button>
            )}
          </div>
        </div>

        {isProcessing ? (
          <Card className="flex items-center gap-3 p-6">
            <Loader2 className="text-ink-muted size-5 animate-spin" />
            <p className="text-ink-muted text-sm">Processing the file — this page updates automatically.</p>
          </Card>
        ) : serviceImport.status === 'failed' ? (
          <Card className="border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {serviceImport.failure_reason ?? 'The import failed.'}
          </Card>
        ) : (
          <div className="flex gap-4 text-sm">
            <Card className="flex-1 p-4">
              <p className="text-ink-muted">Total rows</p>
              <p className="text-ink text-xl font-medium">{serviceImport.total_rows}</p>
            </Card>
            <Card className="flex-1 p-4">
              <p className="text-ink-muted">Will create</p>
              <p className="text-ink text-xl font-medium">{serviceImport.create_count}</p>
            </Card>
            <Card className="flex-1 p-4">
              <p className="text-ink-muted">Will update</p>
              <p className="text-ink text-xl font-medium">{serviceImport.update_count}</p>
            </Card>
            <Card className="flex-1 p-4">
              <p className="text-ink-muted">Errors (skipped)</p>
              <p className="text-ink text-xl font-medium">{serviceImport.error_count}</p>
            </Card>
          </div>
        )}

        {rows.data.length > 0 && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Row</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Notes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.data.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>{row.row_number}</TableCell>
                  <TableCell>{actionBadge(row.action)}</TableCell>
                  <TableCell>{String(row.data.name ?? '')}</TableCell>
                  <TableCell>{String(row.data.category ?? '')}</TableCell>
                  <TableCell>{String(row.data.base_price ?? '')}</TableCell>
                  <TableCell className="text-ink-muted text-xs">
                    {row.errors ? row.errors.join('; ') : '—'}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>
    </>
  );
}

ServiceImportShow.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
