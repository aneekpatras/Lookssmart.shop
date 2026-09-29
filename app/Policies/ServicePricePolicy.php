<?php

namespace App\Policies;

use App\Models\ServicePrice;
use App\Models\User;

class ServicePricePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServicePrice $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, ServicePrice $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, ServicePrice $model): bool
    {
        return $user->can('catalog.manage');
    }
}
