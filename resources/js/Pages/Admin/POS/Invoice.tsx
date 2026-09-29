import { Head, router } from '@inertiajs/react';
import { Printer, FileDown, Ban } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { ReceiptBody, type ReceiptSale } from '@/Components/admin/pos/ReceiptBody';
import { ThermalReceipt } from '@/Components/admin/pos/ThermalReceipt';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import AdminLayout from '@/Layouts/AdminLayout';

/** Scoped to #thermal-receipt-print only (ThermalReceipt.tsx) — printing "Print thermal" must never
 * open the whole admin dashboard/A4 on-screen view, only the 80mm monospace template. */
const THERMAL_PRINT_CSS = `
  @media print {
    body * { visibility: hidden !important; }
    #thermal-receipt-print, #thermal-receipt-print * { visibility: visible !important; }
    #thermal-receipt-print {
      display: block !important;
      position: absolute; left: 0; top: 0;
      width: 80mm !important;
      max-width: 80mm !important;
      font-family: 'Courier New', Courier, monospace !important;
      font-size: 11px !important;
      padding: 0; margin: 0;
    }
    @page { size: 80mm auto; margin: 0; }
  }
`;

const STATUS_VARIANT: Record<string, 'success' | 'accent' | 'destructive'> = {
  completed: 'success',
  open: 'accent',
  refunded: 'destructive',
  voided: 'destructive',
};

interface InvoicePageProps {
  sale: ReceiptSale;
  canVoid: boolean;
}

export default function Invoice({ sale, canVoid }: InvoicePageProps) {
  const [voiding, setVoiding] = React.useState(false);

  function voidSale() {
    if (!window.confirm(`Void sale ${sale.sale_number}? This reverses loyalty points and coupon usage and cannot be undone.`)) {
      return;
    }

    setVoiding(true);
    router.post(`/admin/pos/sales/${sale.id}/void`, {}, {
      onSuccess: () => toast.success('Sale voided.'),
      onError: () => toast.error('Could not void this sale.'),
      onFinish: () => setVoiding(false),
    });
  }

  return (
    <>
      <Head title={`Invoice ${sale.sale_number}`} />
      <style>{THERMAL_PRINT_CSS}</style>

      <div className="mx-auto max-w-2xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Invoice {sale.sale_number}</h1>
            <Badge variant={STATUS_VARIANT[sale.status] ?? 'default'} className="mt-1 capitalize">
              {sale.status}
            </Badge>
          </div>

          <div className="flex flex-wrap gap-2 print:hidden">
            <Button type="button" variant="outline" onClick={() => window.print()}>
              <Printer className="size-4" />
              Print thermal
            </Button>
            <Button type="button" variant="outline" asChild>
              <a href={`/admin/pos/sales/${sale.id}/invoice-pdf`}>
                <FileDown className="size-4" />
                Print A4 / PDF
              </a>
            </Button>
            {canVoid && sale.status === 'completed' && (
              <Button type="button" variant="destructive" disabled={voiding} onClick={voidSale}>
                <Ban className="size-4" />
                Void sale
              </Button>
            )}
          </div>
        </div>

        <Card>
          <CardContent className="p-6">
            <ReceiptBody sale={sale} />
          </CardContent>
        </Card>

        <ThermalReceipt sale={sale} />
      </div>
    </>
  );
}

Invoice.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
