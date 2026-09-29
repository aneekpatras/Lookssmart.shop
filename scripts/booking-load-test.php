<?php

/**
 * Real concurrency/load verification for the booking engine's double-booking guards — genuinely
 * simultaneous HTTP requests via curl_multi, run against a REAL running server and REAL database.
 *
 * WHY THIS EXISTS SEPARATELY FROM tests/Feature/BookingConcurrencyTest.php: that Pest suite runs
 * single-threaded against SQLite `:memory:`, a database private to that one process — no request
 * inside it can ever genuinely overlap another in time, and a real second OS process would get its
 * own empty in-memory database and never see the same seeded slot. Proving actual simultaneity needs
 * requests that are truly in flight on the wire at the same instant, against a database every one of
 * them can actually see and race for — which means a live `php artisan serve` (or a real deployment)
 * and the real MySQL/MariaDB database, not the automated test suite. This mirrors, and makes
 * repeatable, the same manual verification done live in Phase 7 (50 real simultaneous OS processes,
 * 1 success / 49 clean rejections, 0 corruption) — see 02-PROJECT-STATE.md Decision #30 / §3.
 *
 * `/api/booking/*` runs through the full session-based `web` middleware stack (not a stateless
 * token API), so a plain curl POST with no session gets a real 419 CSRF mismatch — the exact error
 * this script hit and was fixed for on first real run. Each simulated "guest" gets its own
 * independently bootstrapped session + CSRF token (a real GET request first, matching what a real
 * browser's first page load does), tracked as an in-memory Cookie header rather than a file,
 * mirroring N genuinely separate browser sessions racing for the same slot rather than N requests
 * replaying one shared session.
 *
 * This script has no database access of its own — it only exercises the HTTP surface. After running
 * it, check the real database yourself to confirm exactly one Booking row exists for the slot
 * (`php artisan tinker --execute="..."`), which is the only thing that actually proves no corruption
 * occurred — a clean HTTP-level result alone (N-1 real 409s) is necessary but not sufficient.
 *
 * Usage:
 *   php scripts/booking-load-test.php <base_url> <staff_id> <service_id> <starts_at ISO8601> <concurrency>
 *
 * Example (staff/service ids and a real bookable slot must already exist in the target database):
 *   php scripts/booking-load-test.php http://127.0.0.1:8000 3 12 2026-09-10T09:00:00+00:00 25
 */
$baseUrl = $argv[1] ?? null;
$staffId = isset($argv[2]) ? (int) $argv[2] : null;
$serviceId = isset($argv[3]) ? (int) $argv[3] : null;
$startsAt = $argv[4] ?? null;
$concurrency = isset($argv[5]) ? max(2, (int) $argv[5]) : 25;

if (! $baseUrl || ! $staffId || ! $serviceId || ! $startsAt) {
    fwrite(STDERR, "Usage: php scripts/booking-load-test.php <base_url> <staff_id> <service_id> <starts_at ISO8601> [concurrency=25]\n");
    exit(1);
}

/**
 * Bootstraps one real session + CSRF token, matching a real browser's first page load. Tracks
 * cookies manually from Set-Cookie response headers rather than curl's file-based cookie-jar
 * mechanism (CURLOPT_COOKIEJAR/COOKIEFILE) — found, via a real failed first run, to silently write
 * an empty jar under this PHP build's curl extension on Windows, with no curl_error() raised at all.
 *
 * @return array{0: string, 1: string} [Cookie header value, X-XSRF-TOKEN header value]
 */
function bootstrapSession(string $baseUrl): array
{
    $ch = curl_init("{$baseUrl}/book");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($status !== 200 || $response === false) {
        fwrite(STDERR, "Failed to bootstrap a session (HTTP {$status} from {$baseUrl}/book)\n");
        exit(1);
    }

    $headers = substr($response, 0, $headerSize);
    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $headers, $matches, PREG_SET_ORDER);

    $cookies = [];
    $xsrfToken = null;
    foreach ($matches as [, $name, $value]) {
        $cookies[] = "{$name}={$value}";
        if ($name === 'XSRF-TOKEN') {
            $xsrfToken = urldecode($value);
        }
    }

    if (! $xsrfToken) {
        fwrite(STDERR, "Could not find an XSRF-TOKEN cookie after bootstrapping a session.\n");
        exit(1);
    }

    return [implode('; ', $cookies), $xsrfToken];
}

function jsonPost(string $url, array $payload, string $cookieHeader, string $xsrfToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "X-XSRF-TOKEN: {$xsrfToken}",
            "Cookie: {$cookieHeader}",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        fwrite(STDERR, "cURL error fetching {$url}: {$error}\n");
        exit(1);
    }

    return [$status, json_decode($body, true)];
}

echo "Bootstrapping a session and requesting a real signed price quote for service #{$serviceId}...\n";
[$quoteCookieHeader, $quoteXsrfToken] = bootstrapSession($baseUrl);
[$quoteStatus, $quote] = jsonPost("{$baseUrl}/api/booking/quote", ['service_ids' => [$serviceId]], $quoteCookieHeader, $quoteXsrfToken);
if ($quoteStatus !== 200) {
    fwrite(STDERR, "Failed to get a quote (HTTP {$quoteStatus}): " . json_encode($quote) . "\n");
    exit(1);
}
echo "Quote OK — total {$quote['total']}, expires_at {$quote['expires_at']}.\n";

echo "Bootstrapping {$concurrency} independent sessions (one per simulated guest)...\n";
$sessions = [];
for ($i = 0; $i < $concurrency; $i++) {
    $sessions[] = bootstrapSession($baseUrl);
}

echo "Firing {$concurrency} genuinely simultaneous POST /api/booking requests at staff #{$staffId}, {$startsAt}...\n";

$multiHandle = curl_multi_init();
$handles = [];
$startTime = microtime(true);

for ($i = 0; $i < $concurrency; $i++) {
    [$cookieHeader, $xsrfToken] = $sessions[$i];

    $payload = [
        'service_ids' => [$serviceId],
        'staff_id' => $staffId,
        'starts_at' => $startsAt,
        'timezone' => 'UTC',
        'guest_name' => "Load Test Racer {$i}",
        'guest_email' => "load-test-racer-{$i}-" . uniqid() . '@example.test',
        'quote' => $quote,
    ];

    $ch = curl_init("{$baseUrl}/api/booking");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "X-XSRF-TOKEN: {$xsrfToken}",
            "Cookie: {$cookieHeader}",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        // Generous: PHP's built-in dev server (`php artisan serve`) is single-threaded and processes
        // one request at a time, so under N truly simultaneous connections the later ones queue
        // behind earlier ones rather than running in parallel — a real production server (PHP-FPM,
        // see DEPLOYMENT.md) has a worker pool and wouldn't serialize like this. A short timeout here
        // would report a false "HTTP 0" for a request that's just waiting its turn, not one the guard
        // rejected — found via a real first run at concurrency=10 with a 20s timeout.
        CURLOPT_TIMEOUT => 90,
    ]);
    curl_multi_add_handle($multiHandle, $ch);
    $handles[] = $ch;
}

// All handles are added to the multi-handle BEFORE any of them are executed — this is what makes
// the requests genuinely overlap on the wire, unlike a sequential foreach of single curl_exec calls.
$running = null;
do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

$elapsed = microtime(true) - $startTime;

$statusCounts = [];
$otherResponses = [];

foreach ($handles as $ch) {
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    if (! in_array($status, [201, 409], true)) {
        $otherResponses[] = [$status, curl_multi_getcontent($ch)];
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}
curl_multi_close($multiHandle);

echo "\nResults ({$concurrency} concurrent attempts on the identical slot, wall-clock time for the whole burst: " . round($elapsed, 3) . "s):\n";
foreach ($statusCounts as $status => $count) {
    echo "  HTTP {$status}: {$count}\n";
}

$created = $statusCounts[201] ?? 0;
$conflicted = $statusCounts[409] ?? 0;

if ($otherResponses !== []) {
    echo "\nUnexpected status codes (should be none — every attempt should be either 201 or 409):\n";
    foreach ($otherResponses as [$status, $body]) {
        echo "  HTTP {$status}: " . substr($body, 0, 300) . "\n";
    }
}

echo "\n" . ($created === 1 && $otherResponses === []
    ? "PASS: exactly one booking created, every other attempt cleanly rejected, no unexpected errors.\n"
    : 'FAIL: expected exactly 1 created and ' . ($concurrency - 1) . " conflicted with zero unexpected statuses — got {$created} created, {$conflicted} conflicted, " . count($otherResponses) . " unexpected.\n");

echo "\nNow verify against the real database — this script cannot see it itself:\n";
echo "  php artisan tinker --execute=\"echo App\\\\Models\\\\Booking::where('staff_id', {$staffId})->where('starts_at', '{$startsAt}')->count();\"\n";
