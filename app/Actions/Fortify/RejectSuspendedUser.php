<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Phase 5 sub-step 4: a suspended admin-panel account must never reach an authenticated session.
 * Inserted into Fortify's login pipeline (`config/fortify.php`) right after `AttemptToAuthenticate`
 * and before `PrepareAuthenticatedSession` — credentials have already been verified and rate
 * limiting/lockout/the `Login` event have already run normally at this point (this does NOT replace
 * `AttemptToAuthenticate`, only adds one more check after it), but the session has not been
 * regenerated/finalized yet, so a suspended user's session is torn down before it ever "counts".
 */
class RejectSuspendedUser
{
    public function __construct(private readonly StatefulGuard $guard) {}

    public function handle($request, $next)
    {
        $user = $this->guard->user();

        if ($user && method_exists($user, 'isSuspended') && $user->isSuspended()) {
            $this->guard->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                Fortify::username() => ['This account has been suspended. Contact an administrator.'],
            ]);
        }

        return $next($request);
    }
}
