<?php

namespace App\Policies;

use App\Models\BusinessHour;
use App\Models\User;

class BusinessHourPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BusinessHour $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, BusinessHour $model): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, BusinessHour $model): bool
    {
        return $user->can('settings.manage');
    }
}
