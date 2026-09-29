<?php

namespace App\Policies;

use App\Models\BookingItem;
use App\Models\User;

class BookingItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') || $user->can('bookings.manage') || $user->can('bookings.view_own');
    }

    public function view(User $user, BookingItem $model): bool
    {
        return app(BookingPolicy::class)->view($user, $model->booking);
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.manage');
    }

    public function update(User $user, BookingItem $model): bool
    {
        return $user->can('bookings.manage');
    }

    public function delete(User $user, BookingItem $model): bool
    {
        return $user->can('bookings.manage');
    }
}
