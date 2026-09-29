<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 sub-step 4 — hand-rolled per the user's explicit decision (not `lab404/laravel-impersonate`),
 * to keep the audit trail on the exact same `activity()` mechanism Phase 4 already built, rather than
 * a package's own logging convention.
 *
 * Security properties, each deliberately enforced here rather than assumed:
 * 1. Super-admin only (`super-admin` role check, not a permission — impersonation is an identity
 *    escalation, not a normal CRUD action, so it isn't gated through the `users.manage` permission
 *    other user-management actions use).
 * 2. No self-impersonation (pointless, and would corrupt the exit-back-to-yourself flow).
 * 3. No NESTED impersonation — session('impersonator_id') already being set means the CURRENT
 *    session is itself an impersonation; starting a second one is refused outright.
 * 4. The exit route is the only way back — it reads `impersonator_id` from the session (never from
 *    client input) and logs back in as that exact original admin, then forgets the session key. A
 *    session that was never impersonating has nothing to exit into.
 * 5. Both the start and the end are audit-logged via the existing spatie/activitylog pipeline, with
 *    the real actor recorded explicitly (activity's automatic `causer` would be the impersonated
 *    user by the time `end()` runs, which is wrong — the actor is the ADMIN, not their target).
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin->hasRole('super-admin'), 403, 'Only a super-admin can impersonate.');

        if ($request->session()->has('impersonator_id')) {
            abort(403, 'You are already impersonating someone — exit that session first.');
        }

        if ($admin->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot impersonate yourself.']);
        }

        activity('users')
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('impersonation started');

        $request->session()->put('impersonator_id', $admin->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        abort_unless($impersonatorId, 403, 'You are not currently impersonating anyone.');

        $impersonatedUser = $request->user();
        $originalAdmin = User::findOrFail($impersonatorId);

        activity('users')
            ->causedBy($originalAdmin)
            ->performedOn($impersonatedUser)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('impersonation ended');

        $request->session()->forget('impersonator_id');

        Auth::login($originalAdmin);
        $request->session()->regenerate();

        return redirect()->route('admin.users');
    }
}
