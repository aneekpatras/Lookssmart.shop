<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Review $model): bool
    {
        return $model->status === 'approved' || $model->customer_id === $user->id || $user->can('crm.manage');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Review $model): bool
    {
        return $user->can('crm.manage');
    }

    public function delete(User $user, Review $model): bool
    {
        return $model->customer_id === $user->id || $user->can('crm.manage');
    }
}
