<?php

namespace App\Policies;

use App\Models\Slider;
use App\Models\User;

class SliderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Slider $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('cms.manage');
    }

    public function update(User $user, Slider $model): bool
    {
        return $user->can('cms.manage');
    }

    public function delete(User $user, Slider $model): bool
    {
        return $user->can('cms.manage');
    }
}
