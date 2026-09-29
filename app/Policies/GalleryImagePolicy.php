<?php

namespace App\Policies;

use App\Models\GalleryImage;
use App\Models\User;

class GalleryImagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GalleryImage $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, GalleryImage $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, GalleryImage $model): bool
    {
        return $user->can('cms.manage');
    }
}
