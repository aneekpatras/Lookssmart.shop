<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Laravel's convention-based auto-discovery (`App\Models\X` → `App\Policies\XPolicy`) only
     * covers models under `App\Models` — `Activity` lives in the spatie/laravel-activitylog
     * package, so it needs registering explicitly (Phase 5 sub-step 4).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Activity::class => ActivityPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // super-admin bypasses every permission check, including ones added after this seeder runs.
        // Explicit permissions are still assigned in RolesAndPermissionsSeeder for auditability.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
