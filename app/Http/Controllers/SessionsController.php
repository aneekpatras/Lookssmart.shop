<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionsController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Customer/Sessions', ['sessions' => $sessions]);
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        // Rotates the remember-me token (invalidates "remember me" cookies elsewhere)...
        Auth::guard('web')->logoutOtherDevices($request->password);

        // ...and removes the other database-driven session rows immediately, since the `database`
        // driver has no other built-in mechanism to invalidate a specific session before it expires.
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'You have been logged out of all other devices.');
    }
}
