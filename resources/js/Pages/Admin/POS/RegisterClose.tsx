import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Printer } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { formatCurrency } from '@/lib/currency';
import { cn } from '@/lib/utils';

interface ShiftSummary {
  id: number;
  staff_name: string | null;
  opening_float: string;
  closing_float: string | null;
  expected_total: string | null;
  counted_total: string | null;
  variance: string | null;
  status: 'open' | 'closed';
  opened_at: string | null;
  closed_at: string | null;
  notes: string | null;
  current_expected_cash: string | null;
}

interface MovementEntry {
  id: number;
  type: 'deposit' | 'withdrawal';
  amount: string;
  reason: string | null;
  staff: string | null;
  created_at: string | null;
}

interface ZReportData {
  shift: ShiftSummary;
  movements: MovementEntry[];
  paymentBreakdown: Record<string, string>;
}

// PKR banknotes (Decision — currency migration): the previous list was literal USD bill/coin
// denominations (quarters, dimes, nickels). PKR retail cash counting is whole-rupee notes only.
const DENOMINATIONS = [5000, 1000, 500, 100, 50, 20, 10];

function CashCountForm({ shift, onClose }: { shift: ShiftSummary; onClose: () => void }) {
  const [counts, setCounts] = React.useState<Record<number, string>>({});
  const form = useForm({ counted_total: '', notes: '' });

  const countedTotal = DENOMINATIONS.reduce((sum, denom) => sum + denom * Number(counts[denom] || 0), 0);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.setData('counted_total', countedTotal.toFixed(2));
    form.post(`/admin/pos/register/${shift.id}/close`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Register closed.');
        onClose();
      },
    });
  }

  const expected = Number(shift.current_expected_cash ?? 0);
  const variance = countedTotal - expected;

  return (
    <form onSubmit={submit} className="space-y-5">
      <div>
        <p className="text-ink-muted mb-2 text-sm font-medium">Counted cash breakdown</p>
        <div className="grid grid-cols-3 gap-3">
          {DENOMINATIONS.map((denom) => (
            <div key={denom} className="space-y-1">
              <Label htmlFor={`denom-${denom}`}>{formatCurrency(denom)}</Label>
              <Input
                id={`denom-${denom}`}
                type="number"
                min="0"
                step="1"
                value={counts[denom] ?? ''}
                onChange={(e) => setCounts((prev) => ({ ...prev, [denom]: e.target.value }))}
              />
            </div>
          ))}
        </div>
      </div>

      <Card>
        <CardContent className="grid grid-cols-3 gap-3 p-4 text-center">
          <div>
            <p className="text-ink-muted text-xs uppercase">Expected cash</p>
            <p className="text-lg font-medium">{formatCurrency(expected)}</p>
          </div>
          <div>
            <p className="text-ink-muted text-xs uppercase">Counted cash</p>
            <p className="text-lg font-medium">{formatCurrency(countedTotal)}</p>
          </div>
          <div>
            <p className="text-ink-muted text-xs uppercase">Variance</p>
            <p className={cn('text-lg font-medium', variance === 0 ? 'text-emerald-600' : 'text-red-600')}>
              {variance > 0 ? '+' : ''}
              {formatCurrency(variance)}
            </p>
          </div>
        </CardContent>
      </Card>

      {variance !== 0 && (
        <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
          {variance > 0 ? 'Overage' : 'Shortage'} detected — please double-check the count before closing.
        </p>
      )}

      <div className="space-y-1.5">
        <Label htmlFor="close-notes">Notes (optional)</Label>
        <Textarea
          id="close-notes"
          rows={2}
          value={form.data.notes}
          onChange={(e) => form.setData('notes', e.target.value)}
        />
      </div>

      <DialogFooter>
        <Button type="submit" disabled={form.processing}>
          Close register
        </Button>
      </DialogFooter>
    </form>
  );
}

function PrintableReport({ report }: { report: ZReportData }) {
  return (
    <div className="space-y-4">
      <div className="text-center">
        <h2 className="font-display text-lg font-medium">Z-Report</h2>
        <p className="text-ink-muted text-xs">
          {report.shift.opened_at ? new Date(report.shift.opened_at).toLocaleString() : ''} &ndash;{' '}
          {report.shift.closed_at ? new Date(report.shift.closed_at).toLocaleString() : ''}
        </p>
        <p className="text-ink-muted text-xs">Staff: {report.shift.staff_name}</p>
      </div>

      <table className="w-full text-sm">
        <tbody>
          <tr>
            <td className="py-1">Opening float</td>
            <td className="py-1 text-right">{formatCurrency(Number(report.shift.opening_float))}</td>
          </tr>
          <tr>
            <td className="py-1">Expected cash</td>
            <td className="py-1 text-right">{formatCurrency(Number(report.shift.expected_total ?? 0))}</td>
          </tr>
          <tr>
            <td className="py-1">Counted cash</td>
            <td className="py-1 text-right">{formatCurrency(Number(report.shift.counted_total ?? 0))}</td>
          </tr>
          <tr className="font-medium">
            <td className="py-1">Variance</td>
            <td className={cn('py-1 text-right', Number(report.shift.variance) === 0 ? 'text-emerald-600' : 'text-red-600')}>
              {formatCurrency(Number(report.shift.variance ?? 0))}
            </td>
          </tr>
        </tbody>
      </table>

      {Object.keys(report.paymentBreakdown).length > 0 && (
        <div>
          <p className="mb-1 text-sm font-medium">Payment method totals</p>
          <table className="w-full text-sm">
            <tbody>
              {Object.entries(report.paymentBreakdown).map(([method, total]) => (
                <tr key={method}>
                  <td className="py-1 capitalize">{method}</td>
                  <td className="py-1 text-right">{formatCurrency(Number(total))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {report.movements.length > 0 && (
        <div>
          <p className="mb-1 text-sm font-medium">Cash movements</p>
          <table className="w-full text-sm">
            <tbody>
              {report.movements.map((movement) => (
                <tr key={movement.id}>
                  <td className="py-1 capitalize">
                    {movement.type} {movement.reason ? `(${movement.reason})` : ''}
                  </td>
                  <td className="py-1 text-right">
                    {movement.type === 'withdrawal' ? '-' : '+'}
                    {formatCurrency(Number(movement.amount))}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export function RegisterCloseDialog({ shift, onClose }: { shift: ShiftSummary; onClose: () => void }) {
  // `shift` is a frozen snapshot from the parent's local state (set once when this dialog was
  // opened) — a background `router.reload()` after closing refreshes the PARENT page's props, but
  // never this prop, so this component needs its own status it can flip the instant a close
  // actually succeeds. Without this, the dialog kept rendering CashCountForm against an
  // already-closed shift instead of ever showing the Z-Report (a real bug, found via E2E testing).
  const [status, setStatus] = React.useState(shift.status);
  const [report, setReport] = React.useState<ZReportData | null>(null);

  const loadReport = React.useCallback(() => {
    axios.get<ZReportData>(`/admin/pos/register/${shift.id}/z-report`).then((response) => setReport(response.data));
  }, [shift.id]);

  React.useEffect(() => {
    if (status === 'closed') {
      loadReport();
    }
  }, [status, loadReport]);

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{status === 'open' ? 'Close register' : 'Z-Report'}</DialogTitle>
        </DialogHeader>

        {status === 'open' ? (
          // Only flips local state here — does NOT also reload the parent page's props. The two
          // used to fire as simultaneous requests the instant the close succeeded, which is
          // unnecessary (the outer Dialog's own onOpenChange below already reloads ['shift',
          // 'recentShifts'] once this dialog is actually dismissed) and, found via WebKit E2E runs
          // specifically, could intermittently starve/skew the immediately-following z-report fetch
          // against this project's single-threaded local dev server (`php artisan serve` — not a
          // production concern, since real deploys run PHP-FPM, but a real, avoidable race regardless
          // of which server is behind it).
          <CashCountForm shift={shift} onClose={() => setStatus('closed')} />
        ) : report ? (
          <>
            <PrintableReport report={report} />
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => window.print()}>
                <Printer className="size-4" />
                Print
              </Button>
            </DialogFooter>
          </>
        ) : (
          <p className="text-ink-muted text-sm">Loading report…</p>
        )}
      </DialogContent>
    </Dialog>
  );
}
