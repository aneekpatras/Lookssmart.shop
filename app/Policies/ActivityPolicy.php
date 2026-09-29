<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * `Activity` lives in the spatie/laravel-activitylog package (`Spatie\Activitylog\Models\Activity`),
 * so Laravel's convention-based policy auto-discovery (`App\Models\X` → `App\Policies\XPolicy`)
 * doesn't find this one automatically — registered explicitly in `AuthServiceProvider`.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, Activity $model): bool
    {
        return $user->can('audit.view');
    }
}
