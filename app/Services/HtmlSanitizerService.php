<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

/**
 * Brief §5 / Phase 4 item 7: a single call site for rich-text sanitization, so it's obvious every
 * caller goes through the same allowlist rather than each feature rolling its own. Sanitize on BOTH
 * save and render — this service doesn't care which call site invokes it, but Phase 10 (CMS) must
 * call it in both places: once before a post is persisted (defense against a stored-XSS payload ever
 * reaching the database) and again when rendering (defense in depth if the allowlist config is ever
 * loosened later, or content was written directly to the DB by something other than the app).
 */
class HtmlSanitizerService
{
    /**
     * @param string $config A profile name from `config('purifier.settings')` — 'default' for
     *                       short/simple fields, 'blog' for full article body content.
     */
    public function clean(string $html, string $config = 'default'): string
    {
        return Purifier::clean($html, $config);
    }
}
