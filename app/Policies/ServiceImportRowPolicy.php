<?php

namespace App\Policies;

use App\Models\ServiceImportRow;
use App\Models\User;

/**
 * Rows are only ever read (in the preview table / error report) — never created, updated, or deleted
 * directly by a user, only by ServicesPreviewImport itself. Same read-only-log pattern as
 * ServicePriceHistoryPolicy/NotificationLogPolicy.
 */
class ServiceImportRowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, ServiceImportRow $model): bool
    {
        return $user->can('catalog.manage');
    }
}
