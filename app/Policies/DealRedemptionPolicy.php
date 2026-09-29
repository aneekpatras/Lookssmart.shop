<?php

namespace App\Policies;

use App\Models\DealRedemption;
use App\Models\User;

class DealRedemptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage') || $user->can('bookings.manage');
    }

    public function view(User $user, DealRedemption $model): bool
    {
        return $user->can('catalog.manage') || $user->can('bookings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.manage');
    }

    public function update(User $user, DealRedemption $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, DealRedemption $model): bool
    {
        return $user->can('catalog.manage');
    }
}
