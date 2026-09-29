<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile verification (Brief §5 anti-abuse on public forms).
 */
class TurnstileService
{
    public function verify(string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret');

        if (blank($secret)) {
            // No real secret configured — fail closed everywhere except local dev, so public forms
            // stay testable without needing real Turnstile keys on a dev machine.
            return app()->environment('local');
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification request failed', ['error' => $e->getMessage()]);

            return false;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
