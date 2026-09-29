<?php

namespace App\Policies;

use App\Models\BookingStatusLog;
use App\Models\User;

class BookingStatusLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') || $user->can('bookings.manage');
    }

    public function view(User $user, BookingStatusLog $model): bool
    {
        return app(BookingPolicy::class)->view($user, $model->booking);
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.manage');
    }

    public function update(User $user, BookingStatusLog $model): bool
    {
        return false;
    }

    public function delete(User $user, BookingStatusLog $model): bool
    {
        return false;
    }
}
