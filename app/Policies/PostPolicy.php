<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Post $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, Post $model): bool
    {
        return $user->can('cms.manage');
    }
}
