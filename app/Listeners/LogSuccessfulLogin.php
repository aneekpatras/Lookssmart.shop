<?php

namespace App\Listeners;

use App\Notifications\SuspiciousLoginDetected;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Session regeneration on login is already handled by Fortify's own pipeline
 * (`PrepareAuthenticatedSession`) — this listener only handles the suspicious-login check
 * (Brief §5). At the moment this fires, the CURRENT request's session row has not been persisted
 * to the `sessions` table yet (that happens at the end of the request), so this query only ever
 * matches genuinely prior sessions — no chicken-and-egg false negative.
 */
class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $ip = Request::ip();

        $seenThisIpBefore = DB::table('sessions')
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('ip_address', $ip)
            ->exists();

        if (! $seenThisIpBefore) {
            // afterResponse(): the login response must never wait on SMTP — see
            // User::sendPasswordResetNotification()'s docblock for why this beats ShouldQueue alone.
            $user = $event->user;
            dispatch(fn () => $user->notify(new SuspiciousLoginDetected($ip, (string) Request::userAgent())))->afterResponse();
        }
    }
}
