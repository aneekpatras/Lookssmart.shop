import { Head, Link, router } from '@inertiajs/react';
import { Play, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface HeldSale {
  id: number;
  sale_number: string;
  customer_name: string | null;
  items_count: number;
  total: string;
  notes: string | null;
  created_at: string | null;
}

export default function HeldSales({ sales }: { sales: HeldSale[] }) {
  const [discardingId, setDiscardingId] = React.useState<number | null>(null);

  function discard(sale: HeldSale) {
    if (!window.confirm(`Discard held sale ${sale.sale_number}? Its cart will be lost.`)) {
      return;
    }

    setDiscardingId(sale.id);
    router.delete(`/admin/pos/held-sales/${sale.id}`, {
      onSuccess: () => toast.success('Held sale discarded.'),
      onError: () => toast.error('Could not discard this held sale.'),
      onFinish: () => setDiscardingId(null),
    });
  }

  return (
    <>
      <Head title="Held Sales" />
      <div className="space-y-4">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Held Sales</h1>
          <p className="text-ink-muted text-sm">Carts parked with the HOLD button in the terminal.</p>
        </div>

        {sales.length === 0 ? (
          <EmptyState title="No held sales" description="Carts you hold at the terminal will show up here." />
        ) : (
          <div className="space-y-3">
            {sales.map((sale) => (
              <Card key={sale.id}>
                <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                  <div>
                    <p className="text-sm font-medium">{sale.sale_number}</p>
                    <p className="text-ink-muted text-xs">
                      {sale.customer_name ?? 'Walk-in Customer'} · {sale.items_count} item{sale.items_count === 1 ? '' : 's'} ·{' '}
                      {sale.created_at ? new Date(sale.created_at).toLocaleString() : ''}
                    </p>
                    {sale.notes && <p className="text-ink-muted text-xs italic">{sale.notes}</p>}
                  </div>

                  <div className="flex items-center gap-3">
                    <span className="text-sm font-medium">{formatCurrency(Number(sale.total))}</span>
                    <Button type="button" size="sm" variant="outline" asChild>
                      <Link href={`/admin/pos/terminal?resume=${sale.id}`}>
                        <Play className="size-4" />
                        Resume
                      </Link>
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={discardingId === sale.id}
                      onClick={() => discard(sale)}
                      aria-label="Discard held sale"
                    >
                      <Trash2 className="text-ink-muted size-4" />
                    </Button>
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

HeldSales.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
