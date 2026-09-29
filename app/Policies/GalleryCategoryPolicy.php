<?php

namespace App\Policies;

use App\Models\GalleryCategory;
use App\Models\User;

class GalleryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GalleryCategory $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, GalleryCategory $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, GalleryCategory $model): bool
    {
        return $user->can('cms.manage');
    }
}
