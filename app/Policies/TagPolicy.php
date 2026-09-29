<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Tag $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, Tag $model): bool
    {
        return $user->can('cms.manage');
    }
}
