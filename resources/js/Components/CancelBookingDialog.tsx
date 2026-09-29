import axios, { isAxiosError } from 'axios';
import { AlertTriangle } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';

export interface CancellableBooking {
  code: string;
  within_cancellation_fee_window: boolean;
}

interface CancelBookingDialogProps {
  booking: CancellableBooking;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called once the real `POST /api/booking/{code}/cancel` succeeds, so the caller can refresh
   * its own booking list — never called optimistically before the server confirms it. */
  onCancelled: () => void;
}

/**
 * The task's exact-wording fee warning, shown only when
 * `booking.within_cancellation_fee_window` is true — the same 24h (admin-configurable) check
 * `CancelBookingAction` itself applies (`Booking::isWithinCancellationWindow()`), so this modal can
 * never show a warning the backend would then silently disagree with, or vice versa.
 */
export function CancelBookingDialog({
  booking,
  open,
  onOpenChange,
  onCancelled,
}: CancelBookingDialogProps) {
  const [reason, setReason] = React.useState('');
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState('');

  function handleOpenChange(next: boolean) {
    if (!next) {
      setReason('');
      setError('');
    }
    onOpenChange(next);
  }

  async function confirmCancel() {
    setBusy(true);
    setError('');

    try {
      await axios.post(`/api/booking/${booking.code}/cancel`, { reason: reason.trim() || undefined });
      handleOpenChange(false);
      onCancelled();
    } catch (err) {
      setError(
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : 'We could not cancel this booking. Please try again or contact the salon directly.',
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Cancel this booking?</DialogTitle>
          <DialogDescription>Booking {booking.code}</DialogDescription>
        </DialogHeader>

        {booking.within_cancellation_fee_window && (
          <div className="flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <AlertTriangle className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>
              Cancellations within 24 hours incur a cancellation charge (or forfeiture of advance
              deposit). Are you sure you wish to proceed?
            </p>
          </div>
        )}

        <div>
          <label htmlFor="cancel-reason" className="text-ink text-sm font-medium">
            Reason <span className="text-ink-muted font-normal">(optional)</span>
          </label>
          <Textarea
            id="cancel-reason"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            placeholder="Let us know why, if you'd like."
            className="mt-2 min-h-20"
          />
        </div>

        {error && (
          <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {error}
          </p>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" disabled={busy} onClick={() => handleOpenChange(false)}>
            Keep booking
          </Button>
          <Button type="button" variant="destructive" disabled={busy} onClick={() => void confirmCancel()}>
            {booking.within_cancellation_fee_window ? 'Yes, cancel anyway' : 'Yes, cancel booking'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
