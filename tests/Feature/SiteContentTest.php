<?php

use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Support\QrCode;
use Database\Seeders\SalonCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Covers the real-content sync: the salon's genuine service catalog (ingested from its own export),
 * the real contact/hours/geo/social settings, the new privacy policy page, and the footer's shared
 * `site` prop. The prop-name assertion in particular guards a bug this work actually hit — naming
 * the shared prop `business` let Contact/PrivacyPolicy's own page-level `business` prop shadow it,
 * leaving the footer reading `hours` off undefined.
 */
/*
 * The catalog-contents tests that used to live here (25 services, the export's variant split, and
 * seeder idempotency) were superseded when the catalog was replaced with the salon's full 55-service
 * menu. They now live in tests/Feature/ServiceCatalogTest.php, which covers the same ground plus the
 * discounted price set, the stock imagery and the per-treatment-family detail guide. Keeping stale
 * copies here would have meant either two conflicting sources of truth or assertions that pass only
 * because they were loosened.
 */

it('excludes the original Base44 sample rows from the catalog', function () {
    $this->seed(SalonCatalogSeeder::class);

    // The 6 starter rows carried USD-scale prices; the cheapest real service is Rs. 150 threading.
    expect(Service::where('base_price', '<', 150)->count())->toBe(0);
    expect(Service::whereIn('name', [
        'Deep Cleansing Facial',
        'Hair Color & Highlights',
        'Relaxing Massage',
        'Classic Haircut',
        'Gel Manicure',
    ])->count())->toBe(0);

    // Every service is bookable: a real duration and a real price, no placeholders.
    expect(Service::where('duration_min', '<=', 0)->orWhere('base_price', '<=', 0)->count())->toBe(0);
    expect(ServiceCategory::count())->toBe(5);
});

it('renders the privacy policy page with all seven sections and real contact details', function () {
    Setting::create(['key' => 'business.name', 'value' => 'Looks Smart Beauty Salon', 'group' => 'business']);
    Setting::create(['key' => 'business.phone', 'value' => '+92 305 9833859', 'group' => 'business']);
    Setting::create(['key' => 'business.email', 'value' => 'lookssmartbeautysalon@gmail.com', 'group' => 'business']);
    Setting::create(['key' => 'business.address', 'value' => '46-B, Commercial Central Park Housing Scheme, Main Ferozpur Road, Lahore', 'group' => 'business']);

    $this->get('/privacy-policy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/PrivacyPolicy')
            ->where('effectiveDate', 'January 2026')
            ->where('business.phone', '+92 305 9833859')
            ->where('business.email', 'lookssmartbeautysalon@gmail.com')
            ->where('business.address', '46-B, Commercial Central Park Housing Scheme, Main Ferozpur Road, Lahore'),
        );
});

it('lists the privacy policy in the sitemap', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(url('/privacy-policy'), escape: false);
});

it('passes real coordinates to the contact page so the map pins the shopfront', function () {
    Setting::create(['key' => 'business.latitude', 'value' => 31.317056, 'group' => 'business']);
    Setting::create(['key' => 'business.longitude', 'value' => 74.389306, 'group' => 'business']);
    Setting::create(['key' => 'business.social_instagram', 'value' => 'https://www.instagram.com/lookssmartbeautysalon/', 'group' => 'business']);

    $this->get('/contact')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('business.latitude', 31.317056)
            ->where('business.longitude', 74.389306)
            ->where('business.instagram', 'https://www.instagram.com/lookssmartbeautysalon/'),
        );
});

it('shares footer contact details under `site`, not a name a page prop could shadow', function () {
    Setting::create(['key' => 'business.name', 'value' => 'Looks Smart Beauty Salon', 'group' => 'business']);
    Setting::create(['key' => 'business.phone', 'value' => '+92 305 9833859', 'group' => 'business']);
    Setting::create(['key' => 'business.social_facebook', 'value' => 'https://www.facebook.com/people/Looks-Smart-Beauty-Salon/61574517810379/', 'group' => 'business']);

    // Contact passes its OWN `business` prop. Both must survive, or the footer breaks on this page.
    $this->get('/contact')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.phone', '+92 305 9833859')
            ->where('site.facebook', 'https://www.facebook.com/people/Looks-Smart-Beauty-Salon/61574517810379/')
            ->has('site.hours')
            ->has('business'),
        );
});

it('collapses a uniform week into a single opening-hours line', function () {
    Cache::forget('business:hours-summary');

    foreach (range(0, 6) as $weekday) {
        BusinessHour::create([
            'weekday' => $weekday,
            'open_time' => '10:00',
            'close_time' => '21:00',
            'is_closed' => false,
        ]);
    }

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.hours', [
                ['days' => 'Monday – Sunday', 'hours' => '10:00 AM – 9:00 PM'],
            ]),
        );
});

it('reports an irregular week honestly instead of flattening it to one claim', function () {
    Cache::forget('business:hours-summary');

    foreach (range(0, 6) as $weekday) {
        BusinessHour::create([
            'weekday' => $weekday,
            'open_time' => $weekday === 0 ? null : '10:00',
            'close_time' => $weekday === 0 ? null : '21:00',
            'is_closed' => $weekday === 0, // Sunday closed.
        ]);
    }

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.hours', [
                ['days' => 'Monday – Saturday', 'hours' => '10:00 AM – 9:00 PM'],
                ['days' => 'Sunday', 'hours' => 'Closed'],
            ]),
        );
});

it('allows the Google Maps embed host in the CSP so the contact map can render', function () {
    $csp = $this->get('/contact')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src https://www.google.com');
    // We still refuse to be framed ourselves — the relaxation is outbound only.
    expect($csp)->toContain("frame-ancestors 'none'");
});

it('passes all six Our Legacy stats to the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('legacyStats', 6)
            ->where('legacyStats.0.value', '12+')
            ->where('legacyStats.0.label', 'Years of Excellence')
            ->where('legacyStats.5.value', '4.9★'),
        );
});

it('derives the WhatsApp number, chat link and QR code all from the business.phone setting', function () {
    Setting::create(['key' => 'business.phone', 'value' => '+92 300 1112223', 'group' => 'business']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('whatsapp.display_phone', '+92 300 1112223')
            // wa.me takes digits only — no plus sign, no spaces.
            ->where('whatsapp.chat_url', 'https://wa.me/923001112223')
            ->where('whatsapp.qr_data_uri', fn (string $uri) => str_starts_with(
                $uri,
                'data:image/svg+xml;base64,',
            )),
        );
});

it('renders the WhatsApp QR as a decodable SVG rather than a third-party image request', function () {
    // An external QR service would be blocked outright by this app's `img-src 'self' data: …` CSP.
    $qr = QrCode::svgDataUri('https://wa.me/923059833859');
    $svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')), true);

    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
    expect($svg)->toContain('<path');
});

it('still exposes a usable WhatsApp link when no phone setting exists', function () {
    // No business.phone row at all — the section must not render an empty `https://wa.me/`.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('whatsapp.chat_url', 'https://wa.me/923059833859')
            ->where('whatsapp.display_phone', '+92 305 9833859'),
        );
});
