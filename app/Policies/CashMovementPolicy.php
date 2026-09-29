<?php

namespace App\Policies;

use App\Models\CashMovement;
use App\Models\User;

class CashMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund');
    }

    public function view(User $user, CashMovement $model): bool
    {
        return $user->id === $model->staff_id || $user->can('pos.refund');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell');
    }

    public function delete(User $user, CashMovement $model): bool
    {
        return $user->can('pos.refund');
    }
}
