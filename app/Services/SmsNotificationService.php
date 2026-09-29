<?php

namespace App\Services;

use App\Models\NotificationLog;

/**
 * Brief §4 / Phase 7 item 8: "optional SMS/WhatsApp." Wired but deliberately INERT — no real Twilio
 * credentials exist (`TWILIO_*` are still empty placeholders, same gap as Google OAuth's blank
 * `client_id`, Phase 3). Never attempts an HTTP call without real credentials: fails closed, logs a
 * `notification_logs` row explaining why, and returns — this is the correct behavior until real
 * Twilio credentials are provided, not a bug to silently work around.
 */
class SmsNotificationService
{
    public function sendBookingConfirmation(string $toPhone, string $bookingCode, bool $whatsapp = false): void
    {
        $channel = $whatsapp ? 'whatsapp' : 'sms';

        if (! $this->isConfigured()) {
            NotificationLog::create([
                'channel' => $channel,
                'recipient' => $toPhone,
                'status' => 'failed',
                'error' => 'Twilio is not configured (TWILIO_SID/TWILIO_AUTH_TOKEN are empty) — message not sent.',
            ]);

            return;
        }

        // No real credentials have ever existed in this environment, so the actual Twilio API call
        // is intentionally not implemented yet — wire it here (via twilio/sdk, not yet installed)
        // once real credentials are provided. Implementing an untestable HTTP call against a service
        // with no real account would be worse than an honest gap: it couldn't be verified to work.
    }

    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid')) && filled(config('services.twilio.auth_token'));
    }
}
