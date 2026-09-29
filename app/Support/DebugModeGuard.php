<?php

namespace App\Support;

use RuntimeException;

/**
 * Brief §5 / Phase 4 item 9: `APP_DEBUG=true` in production exposes stack traces, `.env` values, and
 * raw database queries to any visitor (Laravel's whoops/Ignition debug page). Extracted as a pure
 * function (rather than inline in a service provider) so the exact matrix of environment/debug
 * combinations is directly unit-testable without needing to re-boot the framework in a different env.
 */
class DebugModeGuard
{
    public static function assertSafe(string $environment, bool $debugEnabled): void
    {
        if ($environment === 'production' && $debugEnabled) {
            throw new RuntimeException(
                'APP_DEBUG=true in the production environment. This exposes stack traces, .env '
                . 'values, and database queries to every visitor and must never happen. Refusing to '
                . 'boot until APP_DEBUG=false is set.',
            );
        }
    }
}
