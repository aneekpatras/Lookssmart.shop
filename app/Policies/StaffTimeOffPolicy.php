<?php

namespace App\Policies;

use App\Models\StaffTimeOff;
use App\Models\User;

class StaffTimeOffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.manage') || $user->staff !== null;
    }

    public function view(User $user, StaffTimeOff $model): bool
    {
        return ($user->staff && $user->staff->id === $model->staff_id) || $user->can('staff.manage');
    }

    public function create(User $user): bool
    {
        return $user->staff !== null || $user->can('staff.manage');
    }

    public function update(User $user, StaffTimeOff $model): bool
    {
        return $user->can('staff.manage');
    }

    public function delete(User $user, StaffTimeOff $model): bool
    {
        return ($user->staff && $user->staff->id === $model->staff_id) || $user->can('staff.manage');
    }
}
