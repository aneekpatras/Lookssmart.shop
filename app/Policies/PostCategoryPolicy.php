<?php

namespace App\Policies;

use App\Models\PostCategory;
use App\Models\User;

class PostCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PostCategory $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, PostCategory $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, PostCategory $model): bool
    {
        return $user->can('cms.manage');
    }
}
