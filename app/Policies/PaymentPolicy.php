<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund') || $user->can('bookings.manage') || $user->can('reports.view');
    }

    public function view(User $user, Payment $model): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund') || $user->can('bookings.manage') || $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('bookings.manage');
    }

    public function update(User $user, Payment $model): bool
    {
        return $user->can('pos.refund');
    }

    public function delete(User $user, Payment $model): bool
    {
        return $user->can('pos.refund');
    }
}
