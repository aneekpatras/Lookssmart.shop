import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import AdminLayout from '@/Layouts/AdminLayout';

interface PaymentRow {
  id: number;
  method: string;
  amount: string;
  status: string;
}

interface StatusLogRow {
  from: string | null;
  to: string;
  reason: string | null;
  changed_by: string;
  created_at: string | null;
}

interface BookingDetail {
  id: number;
  code: string;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  staff_name: string | null;
  customer_name: string | null;
  services: string[];
  total: string;
  discount: string;
  tax: string;
  source: string | null;
  notes: string | null;
  cancellation_reason: string | null;
  guest_email: string | null;
  guest_phone: string | null;
  items: { service: string; price: string }[];
  payments: PaymentRow[];
  status_log: StatusLogRow[];
}

/** Mirrors app/Support/BookingStateMachine.php — the server is the real enforcement boundary
 * (an illegal transition comes back as a validation error), this only decides which buttons to show. */
const NEXT_STATUSES: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['checked_in', 'cancelled', 'no_show'],
  checked_in: ['completed', 'cancelled'],
  completed: [],
  cancelled: [],
  no_show: [],
};

const STATUS_LABEL: Record<string, string> = {
  confirmed: 'Confirm',
  checked_in: 'Check in',
  completed: 'Complete',
  cancelled: 'Cancel',
  no_show: 'Mark no-show',
};

export default function BookingShow({ booking }: { booking: BookingDetail }) {
  const [busy, setBusy] = React.useState(false);

  function transition(status: string) {
    const reason = status === 'cancelled' ? window.prompt('Reason for cancelling (optional):') ?? undefined : undefined;

    setBusy(true);
    router.patch(
      `/admin/bookings/${booking.id}/status`,
      { status, reason },
      {
        preserveScroll: true,
        onSuccess: () => toast.success(`Booking marked "${status}".`),
        onError: (errors) => toast.error(errors.status ?? 'Could not update the booking.'),
        onFinish: () => setBusy(false),
      },
    );
  }

  return (
    <>
      <Head title={`Booking ${booking.code}`} />
      <div className="space-y-6">
        <div className="flex items-center justify-between gap-4">
          <div>
            <Link href="/admin/bookings" className="text-ink-muted inline-flex items-center gap-1 text-sm hover:underline">
              <ArrowLeft className="size-4" /> Back to bookings
            </Link>
            <h1 className="font-display text-ink mt-2 text-2xl font-medium">{booking.code}</h1>
          </div>
          <Badge className="capitalize">{booking.status.replace('_', ' ')}</Badge>
        </div>

        <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Appointment</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p><span className="text-ink-muted">Customer:</span> {booking.customer_name ?? '—'}</p>
                <p><span className="text-ink-muted">Staff:</span> {booking.staff_name ?? 'Any available'}</p>
                <p><span className="text-ink-muted">When:</span> {booking.starts_at ? new Date(booking.starts_at).toLocaleString() : '—'}</p>
                <p><span className="text-ink-muted">Source:</span> {booking.source ?? '—'}</p>
                {booking.guest_email && <p><span className="text-ink-muted">Guest email:</span> {booking.guest_email}</p>}
                {booking.guest_phone && <p><span className="text-ink-muted">Guest phone:</span> {booking.guest_phone}</p>}
                {booking.notes && <p><span className="text-ink-muted">Notes:</span> {booking.notes}</p>}
                {booking.cancellation_reason && <p><span className="text-ink-muted">Cancellation reason:</span> {booking.cancellation_reason}</p>}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Services</CardTitle>
              </CardHeader>
              <CardContent>
                <table className="w-full text-sm">
                  <tbody>
                    {booking.items.map((item, index) => (
                      <tr key={index}>
                        <td className="py-1">{item.service}</td>
                        <td className="py-1 text-right">{item.price}</td>
                      </tr>
                    ))}
                    {booking.discount !== '0.00' && (
                      <tr>
                        <td className="py-1">Discount</td>
                        <td className="py-1 text-right">-{booking.discount}</td>
                      </tr>
                    )}
                    <tr>
                      <td className="py-1">Tax</td>
                      <td className="py-1 text-right">{booking.tax}</td>
                    </tr>
                    <tr className="font-medium">
                      <td className="py-1">Total</td>
                      <td className="py-1 text-right">{booking.total}</td>
                    </tr>
                  </tbody>
                </table>
              </CardContent>
            </Card>

            {booking.payments.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>Payments</CardTitle>
                </CardHeader>
                <CardContent className="space-y-1 text-sm">
                  {booking.payments.map((payment) => (
                    <p key={payment.id} className="flex justify-between capitalize">
                      <span>{payment.method.replace('_', ' ')} ({payment.status})</span>
                      <span>{payment.amount}</span>
                    </p>
                  ))}
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader>
                <CardTitle>Status history</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                {booking.status_log.length === 0 ? (
                  <p className="text-ink-muted">No status changes recorded.</p>
                ) : (
                  booking.status_log.map((log, index) => (
                    <p key={index} className="text-ink-muted">
                      <span className="text-ink capitalize">{log.from ?? 'created'} → {log.to.replace('_', ' ')}</span>
                      {' '}by {log.changed_by}
                      {log.created_at ? ` · ${new Date(log.created_at).toLocaleString()}` : ''}
                      {log.reason ? ` · "${log.reason}"` : ''}
                    </p>
                  ))
                )}
              </CardContent>
            </Card>
          </div>

          <Card className="h-fit">
            <CardHeader>
              <CardTitle>Actions</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              {(() => {
                const nextStatuses = NEXT_STATUSES[booking.status] ?? [];

                if (nextStatuses.length === 0) {
                  return <p className="text-ink-muted text-sm">No further actions — this booking is final.</p>;
                }

                return nextStatuses.map((status) => (
                  <Button
                    key={status}
                    type="button"
                    variant={status === 'cancelled' ? 'destructive' : 'outline'}
                    disabled={busy}
                    onClick={() => transition(status)}
                  >
                    {STATUS_LABEL[status] ?? status}
                  </Button>
                ));
              })()}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

BookingShow.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
