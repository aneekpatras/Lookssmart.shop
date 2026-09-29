<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function view(User $user, Message $model): bool
    {
        return $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $model): bool
    {
        return $user->can('crm.manage');
    }

    public function delete(User $user, Message $model): bool
    {
        return $user->can('crm.manage');
    }
}
