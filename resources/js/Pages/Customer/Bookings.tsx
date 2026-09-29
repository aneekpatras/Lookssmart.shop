import { Head, Link, router } from '@inertiajs/react';
import * as React from 'react';

import { CancelBookingDialog } from '@/Components/CancelBookingDialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatCurrency } from '@/lib/currency';

interface Booking {
  id: number;
  code: string;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  services: string[];
  total: string;
  can_cancel: boolean;
  within_cancellation_fee_window: boolean;
}

interface BookingsProps {
  bookings: { data: Booking[]; current_page: number; last_page: number; total: number };
  cancellationWindowHours: number;
}

const STATUS_VARIANT: Record<string, 'outline' | 'success' | 'destructive'> = {
  confirmed: 'success',
  completed: 'success',
  cancelled: 'destructive',
  no_show: 'destructive',
  pending: 'outline',
  checked_in: 'outline',
};

function statusLabel(status: string): string {
  return status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function BookingCard({ booking }: { booking: Booking }) {
  const [cancelOpen, setCancelOpen] = React.useState(false);

  return (
    <Card>
      <CardContent className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h3 className="text-lg">{booking.services.join(', ') || 'Appointment'}</h3>
            <Badge variant={STATUS_VARIANT[booking.status] ?? 'outline'}>
              {statusLabel(booking.status)}
            </Badge>
          </div>
          <p className="text-ink-muted mt-2 text-sm">
            {booking.starts_at ? new Date(booking.starts_at).toLocaleString() : 'Date pending'} ·{' '}
            <span className="font-mono">{booking.code}</span>
          </p>
          <p className="text-ink mt-1 text-sm font-medium">{formatCurrency(booking.total)}</p>
        </div>

        {booking.can_cancel && (
          <div>
            <Button size="sm" variant="destructive" onClick={() => setCancelOpen(true)}>
              Cancel booking
            </Button>
          </div>
        )}
      </CardContent>

      {booking.can_cancel && (
        <CancelBookingDialog
          booking={booking}
          open={cancelOpen}
          onOpenChange={setCancelOpen}
          onCancelled={() => router.reload({ only: ['bookings'] })}
        />
      )}
    </Card>
  );
}

export default function Bookings({ bookings, cancellationWindowHours }: BookingsProps) {
  return (
    <>
      <Head title="My Bookings" />

      <div className="mx-auto max-w-4xl space-y-6 px-6 py-16">
        <div>
          <p className="text-accent-700 text-sm font-semibold uppercase tracking-[0.18em]">
            Your appointments
          </p>
          <h1 className="font-display text-ink mt-2 text-4xl font-medium">My Bookings</h1>
          <p className="text-ink-muted mt-3 text-sm">
            Free cancellation up to {cancellationWindowHours} hours before your appointment. Later
            cancellations may incur a cancellation charge or forfeit your advance deposit.
          </p>
        </div>

        <div className="space-y-4">
          {bookings.data.length ? (
            bookings.data.map((booking) => <BookingCard key={booking.id} booking={booking} />)
          ) : (
            <EmptyState
              title="No bookings yet"
              description="Your appointment history will appear here once you book a visit."
            />
          )}
        </div>

        {bookings.last_page > 1 && (
          <div className="flex items-center justify-between">
            <Button
              variant="outline"
              disabled={bookings.current_page === 1}
              onClick={() =>
                router.get('/my-bookings', { page: bookings.current_page - 1 }, { preserveState: true })
              }
            >
              Previous
            </Button>
            <span className="text-ink-muted text-sm">
              Page {bookings.current_page} of {bookings.last_page}
            </span>
            <Button
              variant="outline"
              disabled={bookings.current_page === bookings.last_page}
              onClick={() =>
                router.get('/my-bookings', { page: bookings.current_page + 1 }, { preserveState: true })
              }
            >
              Next
            </Button>
          </div>
        )}

        <div>
          <Link href="/book" className="text-accent-700 text-sm font-semibold hover:underline">
            Book another visit →
          </Link>
        </div>
      </div>
    </>
  );
}

Bookings.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
