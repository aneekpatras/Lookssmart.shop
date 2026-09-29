<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Phase 10 sub-step 4: single call site for reading/writing grouped admin settings, with transparent
 * encryption for secret fields (API keys, tokens) — never the raw `Setting::get()`/`Setting::create()`
 * calls directly for anything in `SCHEMA` below, so masking and encryption can't be forgotten at a new
 * call site. `Setting`'s own model events (Phase 6) already bust the per-key Redis cache entry on
 * every save/delete, so writing through this service gets that invalidation for free.
 *
 * `SCHEMA` is the allowlist of every key this service will read or write, and its group/type/whether
 * it's encrypted — the settings controller validates every incoming key against it, so a request can
 * never create or overwrite an arbitrary settings row that isn't explicitly declared here.
 *
 * NOTE — Known Issue: the `payments` and `security` groups, plus `integrations.twilio_*`/
 * `integrations.google_maps_api_key`, are genuinely stored and editable through this service, but no
 * other part of the app reads them yet (payment processing is a later POS sub-step; Twilio/Google Maps
 * still come from `.env`/`config()`; the IP allowlist/session lifetime aren't enforced anywhere).
 * `business`/`booking` ARE already live-consumed (AvailabilityEngine, PriceQuoteService,
 * PublicWebsiteController), and as of Phase 13 sub-step 2 so are `integrations.google_analytics_id`/
 * `google_tag_manager_id`/`google_site_verification` (`SeoHead.tsx`, via the shared `seo` Inertia prop).
 * Flagged explicitly rather than silently built as if wired.
 */
class SettingService
{
    public const SCHEMA = [
        'business.name' => ['group' => 'business', 'type' => 'string'],
        'business.phone' => ['group' => 'business', 'type' => 'string'],
        'business.email' => ['group' => 'business', 'type' => 'string'],
        'business.address' => ['group' => 'business', 'type' => 'string'],
        'business.currency' => ['group' => 'business', 'type' => 'string'],
        'business.timezone' => ['group' => 'business', 'type' => 'string'],
        'business.social_facebook' => ['group' => 'business', 'type' => 'string'],
        'business.social_instagram' => ['group' => 'business', 'type' => 'string'],
        'business.logo_path' => ['group' => 'business', 'type' => 'string'],
        'business.favicon_path' => ['group' => 'business', 'type' => 'string'],
        'business.latitude' => ['group' => 'business', 'type' => 'float'],
        'business.longitude' => ['group' => 'business', 'type' => 'float'],

        'booking.slot_minutes' => ['group' => 'booking', 'type' => 'int'],
        'booking.max_advance_days' => ['group' => 'booking', 'type' => 'int'],
        'booking.hold_minutes' => ['group' => 'booking', 'type' => 'int'],
        'booking.min_lead_minutes' => ['group' => 'booking', 'type' => 'int'],
        'booking.cancellation_window_hours' => ['group' => 'booking', 'type' => 'int'],
        'booking.tax_rate' => ['group' => 'booking', 'type' => 'float'],
        // Ad hoc task 30: max simultaneous active/confirmed bookings the salon-wide capacity grid
        // allows per fixed slot (any combination of staff/services) before AvailabilityEngine marks
        // that slot `is_available: false`.
        'booking.max_bookings_per_slot' => ['group' => 'booking', 'type' => 'int'],

        'payments.cash_enabled' => ['group' => 'payments', 'type' => 'bool'],
        'payments.card_enabled' => ['group' => 'payments', 'type' => 'bool'],
        'payments.stripe_public_key' => ['group' => 'payments', 'type' => 'string'],
        'payments.stripe_secret_key' => ['group' => 'payments', 'type' => 'string', 'encrypted' => true],

        'integrations.twilio_sid' => ['group' => 'integrations', 'type' => 'string', 'encrypted' => true],
        'integrations.twilio_auth_token' => ['group' => 'integrations', 'type' => 'string', 'encrypted' => true],
        'integrations.twilio_sms_from' => ['group' => 'integrations', 'type' => 'string'],
        'integrations.twilio_whatsapp_from' => ['group' => 'integrations', 'type' => 'string'],
        'integrations.google_maps_api_key' => ['group' => 'integrations', 'type' => 'string', 'encrypted' => true],
        'integrations.google_analytics_id' => ['group' => 'integrations', 'type' => 'string'],
        'integrations.google_tag_manager_id' => ['group' => 'integrations', 'type' => 'string'],
        'integrations.google_site_verification' => ['group' => 'integrations', 'type' => 'string'],

        'seo.default_title' => ['group' => 'seo', 'type' => 'string'],
        'seo.default_description' => ['group' => 'seo', 'type' => 'string'],

        'security.admin_ip_allowlist' => ['group' => 'security', 'type' => 'string'],
        'security.session_lifetime_minutes' => ['group' => 'security', 'type' => 'int'],
    ];

    public const MASK = '••••••••';

    /**
     * Every key in $group, decrypted, with encrypted values replaced by MASK (or null if unset) —
     * safe to hand straight to the admin UI. Never returns a real secret value.
     */
    public function maskedGroup(string $group): array
    {
        $keys = array_keys(array_filter(self::SCHEMA, fn (array $meta) => $meta['group'] === $group));

        return collect($keys)->mapWithKeys(function (string $key) {
            $meta = self::SCHEMA[$key];
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                return [$key => null];
            }

            if (! empty($meta['encrypted'])) {
                return [$key => self::MASK];
            }

            return [$key => $setting->value];
        })->all();
    }

    /**
     * Validates and writes every key in $values against SCHEMA for $group, skipping any encrypted
     * field left blank (blank means "leave the existing secret unchanged", not "clear it").
     *
     * @return array<string> the keys actually written
     */
    public function updateGroup(string $group, array $values): array
    {
        $written = [];

        foreach ($values as $key => $value) {
            $meta = self::SCHEMA[$key] ?? null;

            if (! $meta || $meta['group'] !== $group) {
                continue;
            }

            if (! empty($meta['encrypted']) && ($value === null || $value === '')) {
                continue;
            }

            $this->set($key, $this->cast($value, $meta['type']), $group, ! empty($meta['encrypted']));
            $written[] = $key;
        }

        return $written;
    }

    public function set(string $key, mixed $value, string $group, bool $encrypted = false): Setting
    {
        $stored = $encrypted ? Crypt::encryptString((string) $value) : $value;

        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group, 'is_encrypted' => $encrypted],
        );
    }

    /**
     * Decrypted read for a single key — for internal/service use once a real consumer (Phase 12+)
     * needs the actual secret value, never for anything rendered back to the browser.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $meta = self::SCHEMA[$key] ?? null;
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($meta && ! empty($meta['encrypted'])) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
