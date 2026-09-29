<?php

namespace App\Policies;

use App\Models\CashRegisterShift;
use App\Models\User;

class CashRegisterShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund');
    }

    public function view(User $user, CashRegisterShift $model): bool
    {
        return $user->id === $model->staff_id || $user->can('pos.refund');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell');
    }

    public function update(User $user, CashRegisterShift $model): bool
    {
        return $user->id === $model->staff_id || $user->can('pos.refund');
    }

    public function delete(User $user, CashRegisterShift $model): bool
    {
        return $user->can('pos.refund');
    }
}
