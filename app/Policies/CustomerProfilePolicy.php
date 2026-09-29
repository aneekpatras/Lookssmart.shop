<?php

namespace App\Policies;

use App\Models\CustomerProfile;
use App\Models\User;

class CustomerProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function view(User $user, CustomerProfile $model): bool
    {
        return $user->id === $model->user_id || $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function update(User $user, CustomerProfile $model): bool
    {
        return $user->id === $model->user_id || $user->can('crm.manage');
    }

    public function delete(User $user, CustomerProfile $model): bool
    {
        return $user->can('crm.manage');
    }
}
