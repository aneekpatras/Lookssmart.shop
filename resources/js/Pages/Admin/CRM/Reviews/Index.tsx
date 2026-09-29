import { Head, router, useForm } from '@inertiajs/react';
import { Check, ShieldCheck, Star, Trash2, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn } from '@/lib/utils';

type ReviewStatus = 'pending' | 'approved' | 'rejected';

interface ReviewRow {
  id: number;
  rating: number;
  title: string | null;
  body: string;
  status: ReviewStatus;
  admin_reply: string | null;
  published_at: string | null;
  customer_name: string | null;
  service_name: string | null;
  booking_code: string | null;
  staff_name: string | null;
  is_verified: boolean;
  created_at: string | null;
}

interface StaffOption {
  id: number;
  name: string;
}

interface ReviewsIndexPageProps {
  reviews: ReviewRow[];
  staffOptions: StaffOption[];
  stats: { pending: number; approved: number; rejected: number };
  filters: {
    status: string | null;
    rating: number | null;
    verified: boolean | null;
    staff_id: number | null;
  };
}

const STATUS_BADGE: Record<ReviewStatus, 'warning' | 'success' | 'destructive'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'destructive',
};

function Stars({ rating }: { rating: number }) {
  return (
    <span className="text-accent-600 inline-flex" aria-label={`${rating} out of 5 stars`}>
      {Array.from({ length: 5 }, (_, i) => (
        <Star key={i} className={cn('size-4', i < rating ? 'fill-current' : 'opacity-30')} />
      ))}
    </span>
  );
}

function FiltersBar({ filters, staffOptions }: { filters: ReviewsIndexPageProps['filters']; staffOptions: StaffOption[] }) {
  function apply(next: Partial<Record<'status' | 'rating' | 'verified' | 'staff_id', string | null>>) {
    router.get(
      '/admin/reviews',
      {
        status: next.status !== undefined ? next.status || undefined : filters.status || undefined,
        rating: next.rating !== undefined ? next.rating || undefined : filters.rating || undefined,
        verified: next.verified !== undefined ? next.verified || undefined : filters.verified === null ? undefined : String(filters.verified),
        staff_id: next.staff_id !== undefined ? next.staff_id || undefined : filters.staff_id || undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="space-y-1.5">
        <Label htmlFor="review-status">Status</Label>
        <Select value={filters.status ?? 'all'} onValueChange={(value) => apply({ status: value === 'all' ? null : value })}>
          <SelectTrigger id="review-status" className="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="approved">Approved</SelectItem>
            <SelectItem value="rejected">Rejected</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="review-rating">Rating</Label>
        <Select value={filters.rating ? String(filters.rating) : 'all'} onValueChange={(value) => apply({ rating: value === 'all' ? null : value })}>
          <SelectTrigger id="review-rating" className="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All ratings</SelectItem>
            {[5, 4, 3, 2, 1].map((r) => (
              <SelectItem key={r} value={String(r)}>
                {r} star{r === 1 ? '' : 's'}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="review-verified">Verified</Label>
        <Select
          value={filters.verified === null ? 'all' : String(filters.verified)}
          onValueChange={(value) => apply({ verified: value === 'all' ? null : value })}
        >
          <SelectTrigger id="review-verified" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All reviews</SelectItem>
            <SelectItem value="true">Verified booking</SelectItem>
            <SelectItem value="false">Unverified</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {staffOptions.length > 0 && (
        <div className="space-y-1.5">
          <Label htmlFor="review-staff">Staff</Label>
          <Select
            value={filters.staff_id ? String(filters.staff_id) : 'all'}
            onValueChange={(value) => apply({ staff_id: value === 'all' ? null : value })}
          >
            <SelectTrigger id="review-staff" className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All staff</SelectItem>
              {staffOptions.map((staff) => (
                <SelectItem key={staff.id} value={String(staff.id)}>
                  {staff.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
    </div>
  );
}

function ReviewDetailDialog({ review, onClose }: { review: ReviewRow; onClose: () => void }) {
  const replyForm = useForm({ admin_reply: review.admin_reply ?? '' });

  function submitReply(event: React.FormEvent) {
    event.preventDefault();
    replyForm.post(`/admin/reviews/${review.id}/reply`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Reply posted.');
        onClose();
      },
    });
  }

  function approve() {
    router.patch(`/admin/reviews/${review.id}/approve`, {}, { preserveScroll: true, onSuccess: onClose });
  }

  function reject() {
    router.patch(`/admin/reviews/${review.id}/reject`, {}, { preserveScroll: true, onSuccess: onClose });
  }

  function destroy() {
    if (confirm('Delete this review?')) {
      router.delete(`/admin/reviews/${review.id}`, { onSuccess: onClose });
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{review.title || 'Review'}</DialogTitle>
        </DialogHeader>

        <div className="flex items-center justify-between">
          <Stars rating={review.rating} />
          <div className="flex items-center gap-2">
            <Badge variant={STATUS_BADGE[review.status]}>{review.status}</Badge>
            {review.is_verified && (
              <Badge variant="outline" className="inline-flex items-center gap-1">
                <ShieldCheck className="size-3" />
                Verified booking
              </Badge>
            )}
          </div>
        </div>

        <div className="text-ink-muted text-sm">
          {review.customer_name ?? 'Guest'}
          {review.service_name ? ` · ${review.service_name}` : ''}
          {review.staff_name ? ` · with ${review.staff_name}` : ''}
        </div>

        <p className="border-border-soft rounded-lg border p-3 text-sm whitespace-pre-line">{review.body}</p>

        <form onSubmit={submitReply} className="space-y-2">
          <Label htmlFor="admin-reply">Public salon response</Label>
          <Textarea
            id="admin-reply"
            rows={3}
            value={replyForm.data.admin_reply}
            onChange={(e) => replyForm.setData('admin_reply', e.target.value)}
          />
          {replyForm.errors.admin_reply && <p className="text-sm text-red-600">{replyForm.errors.admin_reply}</p>}
          <Button type="submit" size="sm" disabled={replyForm.processing || !replyForm.data.admin_reply}>
            Save response
          </Button>
        </form>

        <DialogFooter className="justify-between sm:justify-between">
          <Button type="button" variant="ghost" className="text-red-600 hover:bg-red-50" onClick={destroy}>
            <Trash2 className="size-4" />
            Delete
          </Button>
          <div className="flex gap-2">
            <Button type="button" variant="outline" onClick={reject} disabled={review.status === 'rejected'}>
              <X className="size-4" />
              Reject
            </Button>
            <Button type="button" onClick={approve} disabled={review.status === 'approved'}>
              <Check className="size-4" />
              Approve
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default function ReviewsIndex({ reviews, staffOptions, stats, filters }: ReviewsIndexPageProps) {
  const [openReview, setOpenReview] = React.useState<ReviewRow | null>(null);
  const [selected, setSelected] = React.useState<number[]>([]);

  function toggleSelect(id: number) {
    setSelected((current) => (current.includes(id) ? current.filter((i) => i !== id) : [...current, id]));
  }

  function bulk(action: string) {
    if (selected.length === 0) return;
    router.post(
      '/admin/reviews/bulk',
      { ids: selected, action },
      { preserveScroll: true, onSuccess: () => { toast.success('Reviews updated.'); setSelected([]); } },
    );
  }

  return (
    <>
      <Head title="Reviews" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Reviews</h1>
          <p className="text-ink-muted text-sm">
            {stats.pending} pending · {stats.approved} approved · {stats.rejected} rejected
          </p>
        </div>

        <FiltersBar filters={filters} staffOptions={staffOptions} />

        {selected.length > 0 && (
          <div className="bg-accent-50 flex flex-wrap items-center gap-2 rounded-lg p-2">
            <span className="px-2 text-sm">{selected.length} selected</span>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('approve')}>
              Approve
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => bulk('reject')}>
              Reject
            </Button>
            <Button
              type="button"
              size="sm"
              variant="destructive"
              onClick={() => {
                if (confirm(`Delete ${selected.length} review(s)?`)) bulk('delete');
              }}
            >
              <Trash2 className="size-4" />
              Delete
            </Button>
          </div>
        )}

        <div className="border-border-soft overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-accent-50/60">
              <tr>
                <th className="w-10 p-3" />
                <th className="p-3 text-left">Rating</th>
                <th className="p-3 text-left">Review</th>
                <th className="p-3 text-left">Customer</th>
                <th className="p-3 text-left">Service / Staff</th>
                <th className="p-3 text-left">Status</th>
                <th className="p-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-border-soft divide-y">
              {reviews.map((review) => (
                <tr key={review.id} className="hover:bg-accent-50/40">
                  <td className="p-3" onClick={(e) => e.stopPropagation()}>
                    <Checkbox checked={selected.includes(review.id)} onCheckedChange={() => toggleSelect(review.id)} />
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenReview(review)}>
                    <Stars rating={review.rating} />
                  </td>
                  <td className="max-w-xs cursor-pointer p-3" onClick={() => setOpenReview(review)}>
                    <p className="truncate">{review.title || review.body}</p>
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenReview(review)}>
                    <div className="flex items-center gap-1">
                      {review.customer_name ?? 'Guest'}
                      {review.is_verified && <ShieldCheck className="text-accent-600 size-3.5" />}
                    </div>
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenReview(review)}>
                    {review.service_name ?? '—'}
                    {review.staff_name ? ` · ${review.staff_name}` : ''}
                  </td>
                  <td className="cursor-pointer p-3" onClick={() => setOpenReview(review)}>
                    <Badge variant={STATUS_BADGE[review.status]}>{review.status}</Badge>
                  </td>
                  <td className="p-3">
                    <div className="flex gap-1">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Approve"
                        disabled={review.status === 'approved'}
                        onClick={() => router.patch(`/admin/reviews/${review.id}/approve`, {}, { preserveScroll: true })}
                      >
                        <Check className="size-4 text-emerald-600" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Reject"
                        disabled={review.status === 'rejected'}
                        onClick={() => router.patch(`/admin/reviews/${review.id}/reject`, {}, { preserveScroll: true })}
                      >
                        <X className="size-4 text-red-600" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {reviews.length === 0 && (
                <tr>
                  <td colSpan={7} className="text-ink-muted p-6 text-center text-sm">
                    No reviews found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {openReview && <ReviewDetailDialog review={openReview} onClose={() => setOpenReview(null)} />}
    </>
  );
}

ReviewsIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
