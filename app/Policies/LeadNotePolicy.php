<?php

namespace App\Policies;

use App\Models\LeadNote;
use App\Models\User;

class LeadNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function view(User $user, LeadNote $model): bool
    {
        return $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function delete(User $user, LeadNote $model): bool
    {
        return $user->can('crm.manage');
    }
}
