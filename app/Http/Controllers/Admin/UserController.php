<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 5 sub-step 2: the first real admin controller, and the first place `->authorize()` gets
 * called against the Policy layer built in Phase 4 — closes the remaining (per-action) part of
 * Known Issue #9 for this one resource. Also this sub-step's proof that `DataTable` works against
 * real server-side pagination/sort/search, not just a design in isolation.
 *
 * Sub-step 4 adds invite/assign-role/force-2FA/suspend — everything except impersonation, which is
 * its own controller (a distinct, higher-risk action deserving its own file and its own test file).
 */
class UserController extends Controller
{
    /** Only real admin-panel roles — never `customer`/`guest`, matching Brief §2's access table. */
    private const ADMIN_PANEL_ROLES = ['super-admin', 'admin', 'receptionist', 'staff'];

    private const SORTABLE_COLUMNS = ['name', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'created_at';

        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        $users = User::query()
            ->with('roles:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'email_verified' => $user->email_verified_at !== null,
                'suspended' => $user->isSuspended(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'assignableRoles' => self::ADMIN_PANEL_ROLES,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ADMIN_PANEL_ROLES)],
        ]);

        $signedUrl = URL::temporarySignedRoute('invite.accept.show', now()->addDays(7), [
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        Notification::route('mail', $data['email'])
            ->notify(new AdminInvitation($request->user()->name, $signedUrl));

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'role' => ['required', Rule::in(self::ADMIN_PANEL_ROLES)],
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', "{$user->name}'s role updated to {$data['role']}.");
    }

    public function forceTwoFactor(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', "{$user->name} will be required to set up 2FA again at next login.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot suspend your own account.']);
        }

        $user->forceFill(['suspended_at' => now()])->save();

        return back()->with('success', "{$user->name} has been suspended.");
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->forceFill(['suspended_at' => null])->save();

        return back()->with('success', "{$user->name} has been reinstated.");
    }
}
