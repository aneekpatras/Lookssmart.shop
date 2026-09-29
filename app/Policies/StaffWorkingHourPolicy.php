<?php

namespace App\Policies;

use App\Models\StaffWorkingHour;
use App\Models\User;

class StaffWorkingHourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.manage') || $user->staff !== null;
    }

    public function view(User $user, StaffWorkingHour $model): bool
    {
        return ($user->staff && $user->staff->id === $model->staff_id) || $user->can('staff.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function update(User $user, StaffWorkingHour $model): bool
    {
        return $user->can('staff.manage');
    }

    public function delete(User $user, StaffWorkingHour $model): bool
    {
        return $user->can('staff.manage');
    }
}
