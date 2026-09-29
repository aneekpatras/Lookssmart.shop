<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReviewActionRequest;
use App\Http\Requests\Admin\ReplyToReviewRequest;
use App\Models\Review;
use App\Models\Staff;
use App\Notifications\ReviewStatusUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        // Not ReviewPolicy::viewAny() — that's deliberately open (the public site lists approved
        // reviews without authorization). The admin moderation queue needs a real gate.
        abort_unless($request->user()->can('crm.manage'), 403);

        $reviews = Review::query()
            ->with(['customer:id,name', 'service:id,name', 'booking:id,code,staff_id', 'booking.staff.user:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', $request->integer('rating')))
            ->when($request->filled('verified'), fn ($query) => $request->boolean('verified')
                ? $query->whereNotNull('booking_id')
                : $query->whereNull('booking_id'))
            ->when($request->filled('staff_id'), fn ($query) => $query->whereHas(
                'booking',
                fn ($query) => $query->where('staff_id', $request->integer('staff_id')),
            ))
            ->latest()
            ->get();

        return Inertia::render('Admin/CRM/Reviews/Index', [
            'reviews' => $reviews->map(fn (Review $review) => $this->reviewData($review)),
            'staffOptions' => Staff::active()->with('user:id,name')->get()->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name ?? 'Team member',
            ]),
            'stats' => [
                'pending' => Review::where('status', 'pending')->count(),
                'approved' => Review::where('status', 'approved')->count(),
                'rejected' => Review::where('status', 'rejected')->count(),
            ],
            'filters' => [
                'status' => $request->string('status')->value() ?: null,
                'rating' => $request->integer('rating') ?: null,
                'verified' => $request->filled('verified') ? $request->boolean('verified') : null,
                'staff_id' => $request->integer('staff_id') ?: null,
            ],
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update([
            'status' => 'approved',
            'published_at' => $review->published_at ?? now(),
        ]);

        $this->notifyCustomer($review, 'approved');

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update(['status' => 'rejected']);

        return back()->with('success', 'Review rejected.');
    }

    public function reply(ReplyToReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update(['admin_reply' => $request->validated('admin_reply')]);

        $this->notifyCustomer($review, 'replied');

        return back()->with('success', 'Reply posted.');
    }

    public function bulkAction(BulkReviewActionRequest $request): RedirectResponse
    {
        $reviews = Review::whereIn('id', $request->validated('ids'))->get();
        $action = $request->validated('action');

        foreach ($reviews as $review) {
            if ($action === 'approve') {
                $review->update(['status' => 'approved', 'published_at' => $review->published_at ?? now()]);
                $this->notifyCustomer($review, 'approved');
            } elseif ($action === 'reject') {
                $review->update(['status' => 'rejected']);
            } else {
                $review->delete();
            }
        }

        return back()->with('success', 'Reviews updated.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return back()->with('success', 'Review deleted.');
    }

    private function notifyCustomer(Review $review, string $event): void
    {
        $customer = $review->customer;

        if ($customer) {
            $customer->notify(new ReviewStatusUpdated($review, $event));
        }
    }

    /** @return array<string, mixed> */
    private function reviewData(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'admin_reply' => $review->admin_reply,
            'published_at' => $review->published_at?->toIso8601String(),
            'customer_name' => $review->customer?->name,
            'service_name' => $review->service?->name,
            'booking_code' => $review->booking?->code,
            'staff_name' => $review->booking?->staff?->user?->name,
            'is_verified' => $review->booking_id !== null,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
