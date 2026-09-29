<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Sentry\Laravel\Integration as SentryIntegration;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Phase 14 sub-step 1: SecurityHeaders is `prepend`ed, not `append`ed, and deliberately kept
        // as its own call — confirmed empirically (a route/router middleware introspection dump, not
        // assumed) that append-ing it to the 'web' group here still resolves AFTER route-level
        // middleware like `auth`/`verified`/2FA/`role` for any route that declares its own middleware
        // (i.e. every /admin/* route). Since `auth` throws (not returns) when denying access, that
        // exception unwound the whole request straight out of the pipeline WITHOUT SecurityHeaders
        // ever running at all — meaning the single most common admin request of all, an unauthenticated
        // visit that gets redirected to /login, shipped with zero security headers, CSP included.
        // Prepending puts it ahead of route-level middleware in every case, closing that gap.
        // Phase 14 sub-step 2: TrustProxies is already in Laravel's default global middleware stack,
        // but trusts NO proxies until told to — meaning behind Nginx/a load balancer (the standard
        // production deploy, see DEPLOYMENT.md), every request looks like it arrived insecure/from
        // the proxy's own IP rather than the real client, breaking $request->secure(), the session
        // 'secure' cookie flag, and per-IP rate limiting alike. Empty by default (matches Laravel's
        // own safe default and this app's local dev, which has no proxy in front of it at all) —
        // production sets TRUSTED_PROXIES explicitly (its own IP for same-host Nginx, or the load
        // balancer's real IP/CIDR for a multi-host deploy). Never '*' here by default: trusting every
        // proxy unconditionally would let a request that reaches the app directly (bypassing Nginx
        // entirely, e.g. a misconfigured firewall) spoof its own IP/scheme via X-Forwarded-* headers.
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))));

        $middleware->web(prepend: [
            SecurityHeaders::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            'throttle:fortify-routes',
        ]);

        // Browsers send CSP violation reports without our CSRF token — this is the one deliberate
        // exemption in the app, scoped as narrowly as possible.
        $middleware->validateCsrfTokens(except: [
            'csp-report',
        ]);

        // spatie/laravel-permission's built-in role gate — applied to routes/admin.php as a stopgap
        // route-level check (audit finding, 2026-08-26). Per-action ->authorize() calls against the
        // Policy layer built in Phase 4 are still Phase 5/6's job; this only closes the "any
        // authenticated, 2FA-confirmed account — including a plain customer — can load every /admin/*
        // page shell" gap.
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Phase 14 sub-step 1: a genuine unhandled exception (not just abort(403)/404/419/503) still
        // produces status 500 here, so it was already caught by the in_array() branch below — a
        // second `$status === 500` check used to follow it, gated on `! hasDebugModeEnabled()`, but
        // that condition could never be true (the branch above already returns for every status-500
        // response, debug mode or not). Removed as genuinely unreachable dead code, not a behavior
        // change: `Errors/500.tsx` never rendered anything but a generic, prop-free message either
        // way, so stack-trace suppression for the Inertia path was never actually conditional on
        // debug mode — it's unconditional, which is the correct, safer behavior anyway.
        // Phase 14 sub-step 2. Inert with no SENTRY_LARAVEL_DSN configured (the Sentry SDK is a no-op
        // when its DSN is blank — same wired-but-inert pattern already used for Twilio SMS and Google
        // Calendar OAuth) — reports every exception the app's own handler already reports, including
        // ones caught and re-rendered inside SecurityHeaders::handle()'s try/catch, since that code
        // still calls report() explicitly.
        SentryIntegration::handles($exceptions);

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->header('X-Inertia') && ! app()->environment('testing') && in_array($status, [403, 404, 419, 500, 503], true)) {
                $page = match (true) {
                    $status === 503 => '500',
                    default => (string) $status,
                };

                $response = Inertia::render("Errors/{$page}")
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            // Fallback only — a request that matched a real route already got the full header set
            // from SecurityHeaders (it wraps the route's own middleware, including exceptions any of
            // them throw). A path matching NO route at all never reaches that middleware in the first
            // place, since there's no route to attach it to: Laravel's router raises its own
            // NotFoundHttpException before route middleware resolution ever runs. That was a real,
            // live-confirmed gap (curled a nonexistent path, got a 404 with zero security headers,
            // CSP included) — every unmatched-path 404 a scanner/bot naturally generates is exactly
            // this case, so it's not a rare corner. A fresh nonce here is unused by anything (this
            // fallback only ever serves Laravel's own generic error output, never one of our nonce'd
            // script tags) but keeps script-src meaningful rather than nonce-less.
            if (! $response->headers->has('Content-Security-Policy')) {
                SecurityHeaders::apply($response, Str::random(32), $request->secure());
            }

            return $response;
        });
    })->create();
