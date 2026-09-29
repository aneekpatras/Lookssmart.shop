<?php

namespace App\Policies;

use App\Models\ReminderRule;
use App\Models\User;

class ReminderRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, ReminderRule $model): bool
    {
        return $user->can('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, ReminderRule $model): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, ReminderRule $model): bool
    {
        return $user->can('settings.manage');
    }
}
