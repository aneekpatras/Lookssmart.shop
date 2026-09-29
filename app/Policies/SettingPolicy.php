<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, Setting $model): bool
    {
        return $user->can('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, Setting $model): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, Setting $model): bool
    {
        return $user->can('settings.manage');
    }
}
