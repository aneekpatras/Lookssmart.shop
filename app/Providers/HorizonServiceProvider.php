<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments. Interim policy until
     * Phase 3 (auth/roles) and Phase 5 (RBAC) land: an env-configured email allowlist, deny-by-default.
     * Replace with `$user->hasRole('super-admin')` once spatie/laravel-permission is wired up — see
     * 02-PROJECT-STATE.md §10 for the tracking note.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            $allowed = array_filter(explode(',', (string) config('horizon.admin_emails')));

            return $user !== null && in_array($user->email, $allowed, true);
        });
    }
}
