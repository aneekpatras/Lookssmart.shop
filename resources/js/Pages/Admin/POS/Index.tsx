import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Minus, Plus, Unlock } from 'lucide-react';
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
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';
import { RegisterCloseDialog } from './RegisterClose';

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

interface PosIndexPageProps {
  shift: ShiftSummary | null;
  recentShifts: ShiftSummary[];
}


function OpenRegisterModal() {
  const [open, setOpen] = React.useState(false);
  const form = useForm({ opening_float: '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post('/admin/pos/register/open', {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Register opened.');
        setOpen(false);
        form.reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <Button type="button" onClick={() => setOpen(true)}>
        <Unlock className="size-4" />
        Open register
      </Button>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Open register</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="opening_float">Starting cash float</Label>
            <Input
              id="opening_float"
              type="number"
              step="0.01"
              min="0"
              value={form.data.opening_float}
              onChange={(e) => form.setData('opening_float', e.target.value)}
              aria-invalid={!!form.errors.opening_float}
              required
            />
            {form.errors.opening_float && <p className="text-sm text-red-600">{form.errors.opening_float}</p>}
          </div>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}>
              Open
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CashMovementModal({ shift, direction }: { shift: ShiftSummary; direction: 'in' | 'out' }) {
  const [open, setOpen] = React.useState(false);
  const form = useForm({ amount: '', reason: '' });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const url = `/admin/pos/register/${shift.id}/cash-${direction}`;
    form.post(url, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(direction === 'in' ? 'Cash added.' : 'Cash removed.');
        setOpen(false);
        form.reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <Button type="button" variant="outline" onClick={() => setOpen(true)}>
        {direction === 'in' ? <Plus className="size-4" /> : <Minus className="size-4" />}
        {direction === 'in' ? 'Add cash' : 'Remove cash'}
      </Button>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{direction === 'in' ? 'Add cash to drawer' : 'Remove cash from drawer'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor={`amount-${direction}`}>Amount</Label>
            <Input
              id={`amount-${direction}`}
              type="number"
              step="0.01"
              min="0.01"
              value={form.data.amount}
              onChange={(e) => form.setData('amount', e.target.value)}
              aria-invalid={!!form.errors.amount}
              required
            />
            {form.errors.amount && <p className="text-sm text-red-600">{form.errors.amount}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`reason-${direction}`}>Reason</Label>
            <Input
              id={`reason-${direction}`}
              value={form.data.reason}
              onChange={(e) => form.setData('reason', e.target.value)}
              placeholder="e.g. Change fund top-up"
            />
          </div>
          <DialogFooter>
            <Button type="submit" disabled={form.processing}>
              Confirm
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function PosIndex({ shift, recentShifts }: PosIndexPageProps) {
  const [closingShift, setClosingShift] = React.useState<ShiftSummary | null>(null);

  return (
    <>
      <Head title="POS Register" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Point of Sale</h1>
          <p className="text-ink-muted text-sm">Register sessions, cash drawer, and Z-Reports.</p>
        </div>

        {shift ? (
          <Card>
            <CardContent className="space-y-4 p-6">
              <div className="flex items-center justify-between">
                <div>
                  <Badge variant="accent">Register open</Badge>
                  <p className="mt-2 text-sm">
                    Opened by <span className="font-medium">{shift.staff_name}</span> at{' '}
                    {shift.opened_at ? new Date(shift.opened_at).toLocaleString() : ''}
                  </p>
                </div>
                <Button type="button" variant="destructive" onClick={() => setClosingShift(shift)}>
                  <Lock className="size-4" />
                  Close register
                </Button>
              </div>

              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                  <p className="text-ink-muted text-xs uppercase">Opening float</p>
                  <p className="text-lg font-medium">{formatCurrency(Number(shift.opening_float))}</p>
                </div>
                <div>
                  <p className="text-ink-muted text-xs uppercase">Expected cash now</p>
                  <p className="text-lg font-medium">{formatCurrency(Number(shift.current_expected_cash ?? 0))}</p>
                </div>
              </div>

              <div className="flex gap-2">
                <CashMovementModal shift={shift} direction="in" />
                <CashMovementModal shift={shift} direction="out" />
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardContent className="space-y-4 p-6 text-center">
              <EmptyState title="No register open" description="Open the register with a starting cash float to begin." />
              <OpenRegisterModal />
            </CardContent>
          </Card>
        )}

        <div>
          <h2 className="font-display text-ink mb-3 text-lg font-medium">Recent shifts</h2>
          {recentShifts.length === 0 ? (
            <p className="text-ink-muted text-sm">No closed shifts yet.</p>
          ) : (
            <div className="border-border-soft overflow-x-auto rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-accent-50/60">
                  <tr>
                    <th className="p-3 text-left">Staff</th>
                    <th className="p-3 text-left">Closed</th>
                    <th className="p-3 text-left">Expected</th>
                    <th className="p-3 text-left">Counted</th>
                    <th className="p-3 text-left">Variance</th>
                    <th className="p-3 text-left" />
                  </tr>
                </thead>
                <tbody className="divide-border-soft divide-y">
                  {recentShifts.map((row) => (
                    <tr key={row.id}>
                      <td className="p-3">{row.staff_name}</td>
                      <td className="p-3">{row.closed_at ? new Date(row.closed_at).toLocaleString() : ''}</td>
                      <td className="p-3">{formatCurrency(Number(row.expected_total ?? 0))}</td>
                      <td className="p-3">{formatCurrency(Number(row.counted_total ?? 0))}</td>
                      <td className="p-3">{formatCurrency(Number(row.variance ?? 0))}</td>
                      <td className="p-3 text-right">
                        <Button type="button" size="sm" variant="outline" onClick={() => setClosingShift(row)}>
                          View Z-Report
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {closingShift && (
        <RegisterCloseDialog
          shift={closingShift}
          onClose={() => {
            setClosingShift(null);
            router.reload({ only: ['shift', 'recentShifts'] });
          }}
        />
      )}
    </>
  );
}

PosIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
