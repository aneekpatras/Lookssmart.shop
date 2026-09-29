import axios from 'axios';
import { Printer } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { ReceiptBody, type ReceiptSale } from '@/Components/admin/pos/ReceiptBody';
import { ThermalReceipt } from '@/Components/admin/pos/ThermalReceipt';

export type { ReceiptSale };

/**
 * 80mm thermal roll layout — scoped to #thermal-receipt-print only (ThermalReceipt.tsx's own
 * monospace/dashed-line template). `visibility: hidden` on the whole page alone is NOT enough here:
 * Radix's `DialogOverlay`/`DialogContent` are both `position: fixed` (needed to center/backdrop the
 * modal on screen), and Chromium's print engine can still paint a `position: fixed` box even when an
 * ancestor sets `visibility: hidden` on it — a real, reproduced bug (the on-screen ReceiptBody
 * preview + dialog chrome printed ALONGSIDE the thermal receipt instead of being suppressed). Fixed
 * with an explicit `display: none` on every direct child of the dialog's `role="dialog"` content
 * except the thermal `<pre>` itself — `display: none` has no such fixed-position print quirk.
 */
const THERMAL_PRINT_CSS = `
  @media print {
    body * { visibility: hidden !important; }
    [role="dialog"] > *:not(#thermal-receipt-print) { display: none !important; }
    #thermal-receipt-print, #thermal-receipt-print * { visibility: visible !important; }
    #thermal-receipt-print {
      display: block !important;
      position: absolute; left: 0; top: 0;
      width: 80mm !important;
      max-width: 80mm !important;
      font-family: 'Courier New', Courier, monospace !important;
      font-size: 11px !important;
      color: #000 !important;
      background: #fff !important;
      padding: 0; margin: 0;
    }
    @page { size: 80mm auto; margin: 0; }
  }
`;

/**
 * Always re-fetches by id rather than trusting the checkout response directly, so the printed
 * receipt is provably what actually landed in the database — same "detail endpoint" pattern as
 * RegisterClose.tsx's Z-Report. Auto-prints once the sale loads (the thermal roll's whole point is
 * a fast, hands-off print right after the sale completes); the Print button stays as a manual
 * fallback if that first print was dismissed or the wrong printer was selected.
 */
export function ReceiptDialog({ saleId, onClose }: { saleId: number; onClose: () => void }) {
  const [sale, setSale] = React.useState<ReceiptSale | null>(null);
  const printedRef = React.useRef(false);

  React.useEffect(() => {
    axios.get<{ sale: ReceiptSale }>(`/admin/pos/sales/${saleId}/receipt`).then((response) => setSale(response.data.sale));
  }, [saleId]);

  React.useEffect(() => {
    if (sale && !printedRef.current) {
      printedRef.current = true;
      window.print();
    }
  }, [sale]);

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <style>{THERMAL_PRINT_CSS}</style>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Receipt</DialogTitle>
        </DialogHeader>

        {sale ? (
          <>
            <ReceiptBody sale={sale} />
            <ThermalReceipt sale={sale} />

            <DialogFooter>
              <Button type="button" variant="outline" asChild>
                <a href={`/admin/pos/sales/${sale.id}`}>View invoice</a>
              </Button>
              <Button type="button" variant="outline" onClick={() => window.print()}>
                <Printer className="size-4" />
                Print
              </Button>
              <Button type="button" onClick={onClose}>
                New sale
              </Button>
            </DialogFooter>
          </>
        ) : (
          <p className="text-ink-muted text-sm">Loading receipt&hellip;</p>
        )}
      </DialogContent>
    </Dialog>
  );
}
