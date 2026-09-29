<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Gallery $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Gallery $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, Gallery $model): bool
    {
        return $user->can('cms.manage');
    }
}
