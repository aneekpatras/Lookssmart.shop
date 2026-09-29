<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Deliberately excludes `bookings.view_own` (unlike `view()` below) — "view any" means the
     * admin-wide list (Phase 14's AdminBookingController), a materially different capability from a
     * staff member owning/being assigned to one specific booking. `bookings.view_own` alone must not
     * unlock the full unfiltered list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') || $user->can('bookings.manage');
    }

    public function view(User $user, Booking $model): bool
    {
        return $this->owns($user, $model) || $user->can('bookings.view') || $user->can('bookings.manage');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Booking $model): bool
    {
        return $this->owns($user, $model) || $user->can('bookings.manage');
    }

    public function delete(User $user, Booking $model): bool
    {
        return $model->customer_id === $user->id || $user->can('bookings.manage');
    }

    private function owns(User $user, Booking $model): bool
    {
        return $model->customer_id === $user->id
            || ($user->staff && $user->staff->id === $model->staff_id);
    }
}
