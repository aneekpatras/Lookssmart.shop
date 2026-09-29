<?php

namespace App\Policies;

use App\Models\SaleItem;
use App\Models\User;

class SaleItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.sell') || $user->can('pos.refund') || $user->can('reports.view');
    }

    public function view(User $user, SaleItem $model): bool
    {
        return app(SalePolicy::class)->view($user, $model->sale);
    }

    public function create(User $user): bool
    {
        return $user->can('pos.sell');
    }

    public function update(User $user, SaleItem $model): bool
    {
        return $user->can('pos.refund');
    }

    public function delete(User $user, SaleItem $model): bool
    {
        return $user->can('pos.refund');
    }
}
