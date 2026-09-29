<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deal $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Deal $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Deal $model): bool
    {
        return $user->can('catalog.manage');
    }
}
