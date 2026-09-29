<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund') || $user->can('reports.view');
    }

    public function view(User $user, Sale $model): bool
    {
        return $this->processedBy($user, $model) || $user->can('pos.sell') || $user->can('pos.refund') || $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell');
    }

    public function update(User $user, Sale $model): bool
    {
        return $user->can('pos.sell');
    }

    public function refund(User $user, Sale $model): bool
    {
        return $user->can('pos.refund');
    }

    public function delete(User $user, Sale $model): bool
    {
        return $user->can('pos.refund');
    }

    private function processedBy(User $user, Sale $model): bool
    {
        return $model->created_by === $user->id
            || ($user->staff && $user->staff->id === $model->staff_id);
    }
}
