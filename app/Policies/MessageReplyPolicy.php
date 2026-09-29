<?php

namespace App\Policies;

use App\Models\MessageReply;
use App\Models\User;

class MessageReplyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function view(User $user, MessageReply $model): bool
    {
        return $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function delete(User $user, MessageReply $model): bool
    {
        return $user->can('crm.manage');
    }
}
