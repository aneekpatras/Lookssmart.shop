<?php

namespace App\Policies;

use App\Models\ServiceImport;
use App\Models\User;

class ServiceImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, ServiceImport $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, ServiceImport $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, ServiceImport $model): bool
    {
        return $user->can('catalog.manage');
    }
}
