<?php

namespace App\Policies;

use App\Models\SalonHoliday;
use App\Models\User;

class SalonHolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalonHoliday $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, SalonHoliday $model): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, SalonHoliday $model): bool
    {
        return $user->can('settings.manage');
    }
}
