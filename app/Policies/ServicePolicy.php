<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Service $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Service $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Service $model): bool
    {
        return $user->can('catalog.manage');
    }
}
