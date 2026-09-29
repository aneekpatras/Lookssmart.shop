<?php

namespace App\Policies;

use App\Models\ServicePriceHistory;
use App\Models\User;

/**
 * Read-only audit log, same pattern as NotificationLogPolicy — no create/update/delete via the
 * policy layer since rows are only ever written by ServicePriceController itself, never by a user
 * acting on the model directly.
 */
class ServicePriceHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, ServicePriceHistory $model): bool
    {
        return $user->can('catalog.manage');
    }
}
