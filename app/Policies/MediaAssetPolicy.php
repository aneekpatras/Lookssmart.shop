<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * Unlike Slider/Post/Gallery (public-readable, cms.manage to mutate), the Media Library has no public
 * consumer at all — it's purely an admin tool — so every action, including viewing, requires
 * cms.manage.
 */
class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function view(User $user, MediaAsset $model): bool
    {
        return $user->can('cms.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, MediaAsset $model): bool
    {
        return $user->can('cms.manage');
    }
}
