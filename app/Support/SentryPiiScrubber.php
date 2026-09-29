<?php

namespace App\Support;

use Sentry\Event;

/**
 * Brief §5's Sentry `beforeSend` PII-scrubbing requirement. A static method reference (not a
 * closure) so `config/sentry.php` stays `config:cache`-able — `artisan config:cache` serializes
 * every config value via `var_export()`, which cannot represent a Closure object; found as a real
 * regression (`DebugModeGuardTest`'s config-cache test) when this was first written as an inline
 * closure. `send_default_pii = false` (also in config/sentry.php) already keeps Sentry from
 * attaching cookies/request body/client IP by default — this is defense-in-depth on top of that.
 */
class SentryPiiScrubber
{
    public static function scrub(Event $event): Event
    {
        $request = $event->getRequest();

        foreach (['Authorization', 'Cookie', 'X-XSRF-TOKEN'] as $header) {
            if (isset($request['headers'][$header])) {
                $request['headers'][$header] = '[Filtered]';
            }
        }

        unset($request['cookies']);

        if (is_array($request['data'] ?? null)) {
            foreach (['password', 'password_confirmation', 'current_password', 'two_factor_code', 'two_factor_recovery_code', 'card_number', 'cvv', 'secret'] as $field) {
                if (array_key_exists($field, $request['data'])) {
                    $request['data'][$field] = '[Filtered]';
                }
            }
        }

        $event->setRequest($request);

        return $event;
    }
}
