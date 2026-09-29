<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

class ServiceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceCategory $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, ServiceCategory $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, ServiceCategory $model): bool
    {
        return $user->can('catalog.manage');
    }
}
