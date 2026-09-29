<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function view(User $user, Lead $model): bool
    {
        return $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $model): bool
    {
        return $user->can('crm.manage');
    }

    public function delete(User $user, Lead $model): bool
    {
        return $user->can('crm.manage');
    }
}
