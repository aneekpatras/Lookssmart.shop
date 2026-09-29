<?php

namespace App\Policies;

use App\Models\Slide;
use App\Models\User;

class SlidePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Slide $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Slide $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, Slide $model): bool
    {
        return $user->can('cms.manage');
    }
}
