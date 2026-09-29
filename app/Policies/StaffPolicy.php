<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Staff $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function update(User $user, Staff $model): bool
    {
        return $model->user_id === $user->id || $user->can('staff.manage');
    }

    public function delete(User $user, Staff $model): bool
    {
        return $user->can('staff.manage');
    }
}
