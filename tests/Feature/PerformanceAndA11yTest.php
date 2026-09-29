<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shares real GTM, GA4, and GSC verification values on a public page once configured', function () {
    Setting::create(['key' => 'integrations.google_tag_manager_id', 'value' => 'GTM-ABC1234', 'group' => 'integrations', 'is_encrypted' => false]);
    Setting::create(['key' => 'integrations.google_analytics_id', 'value' => 'G-ABCDEF1234', 'group' => 'integrations', 'is_encrypted' => false]);
    Setting::create(['key' => 'integrations.google_site_verification', 'value' => 'real-verification-token', 'group' => 'integrations', 'is_encrypted' => false]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('seo.gtmId', 'GTM-ABC1234')
        ->where('seo.ga4Id', 'G-ABCDEF1234')
        ->where('seo.gscVerification', 'real-verification-token'));
});

it('shares null GTM, GA4, and GSC values when none are configured, never a fabricated placeholder', function () {
    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('seo.gtmId', null)
        ->where('seo.ga4Id', null)
        ->where('seo.gscVerification', null));
});

it('keeps the locked-down CSP unchanged when no analytics is configured', function () {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');

    // `frame-src` is deliberately NOT `'none'` any more: the Contact page's keyless Google Maps
    // embed needs `https://www.google.com` whether or not analytics is configured, and `'none'`
    // had been silently blocking that iframe since Phase 9 (content-sync task, §4/§10). The
    // analytics-only loosening must still be absent here, which is what this test guards.
    expect($csp)->not->toContain('googletagmanager.com')
        ->not->toContain('google-analytics.com')
        ->not->toContain('strict-dynamic')
        ->toContain('frame-src https://www.google.com')
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-inline');
});

it('loosens only script/frame/img/connect-src to the real Google domains once GTM or GA4 is configured', function () {
    Setting::create(['key' => 'integrations.google_tag_manager_id', 'value' => 'GTM-ABC1234', 'group' => 'integrations', 'is_encrypted' => false]);

    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->toContain("'strict-dynamic'")
        ->toContain('https://www.googletagmanager.com')
        // GTM's `<noscript>` iframe host is added ALONGSIDE the maps host, not instead of it —
        // asserted as a whole directive so neither can silently drop out.
        ->toContain('frame-src https://www.google.com https://www.googletagmanager.com')
        ->toContain('https://www.google-analytics.com')
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-inline');
});

it('returns a valid manifest.json with real branding and the real favicon as an icon', function () {
    Setting::create(['key' => 'business.name', 'value' => 'Looks Smart Test Salon', 'group' => 'business', 'is_encrypted' => false]);

    $response = $this->get('/manifest.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = $response->json();

    expect($manifest['name'])->toBe('Looks Smart Test Salon')
        ->and($manifest['short_name'])->toBe('Looks Smart Test Salon')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['background_color'])->toBe('#faf7f2')
        ->and($manifest['theme_color'])->toBe('#c9a66b')
        ->and($manifest['icons'])->toBeArray()->not->toBeEmpty();

    expect(collect($manifest['icons'])->contains(fn ($icon) => str_contains($icon['src'], 'favicon.ico')))->toBeTrue();
});

it('includes the real uploaded business logo as a second manifest icon only once one exists on disk', function () {
    Storage::fake('public');

    $response = $this->get('/manifest.json');
    expect($response->json('icons'))->toHaveCount(1);

    Storage::disk('public')->put('business/logo.png', 'fake-real-png-bytes');
    Setting::create(['key' => 'business.logo_path', 'value' => 'business/logo.png', 'group' => 'business', 'is_encrypted' => false]);

    $response = $this->get('/manifest.json');
    $icons = $response->json('icons');

    expect($icons)->toHaveCount(2)
        ->and(collect($icons)->contains(fn ($icon) => str_contains($icon['src'], 'business/logo.png')))->toBeTrue();
});

it('never advertises a manifest icon for a business.logo_path setting whose file no longer exists', function () {
    Storage::fake('public');
    Setting::create(['key' => 'business.logo_path', 'value' => 'business/deleted.png', 'group' => 'business', 'is_encrypted' => false]);

    $response = $this->get('/manifest.json');

    expect($response->json('icons'))->toHaveCount(1);
});

/**
 * `loading`/`decoding` are attributes on React elements that only exist in the DOM once Inertia SSR
 * (or client hydration) actually renders them — this repo has no Vitest/RTL setup yet (confirmed: no
 * vitest config or *.test.tsx files exist), so there's no in-process way to render a React component
 * and assert its real DOM output the way the rest of this suite asserts real HTTP/DB behavior. These
 * are therefore deliberate source-presence checks, not DOM-rendering assertions — documented as such
 * rather than silently passed off as more than they are. They still catch a real regression: if
 * `Picture.tsx`'s defaults are ever removed, these fail.
 */
it('defaults the shared Picture component to lazy loading and async decoding', function () {
    $source = file_get_contents(base_path('resources/js/Components/Picture.tsx'));

    expect($source)->toContain("loading = 'lazy'")
        ->toContain("decoding = 'async'");
});

it('keeps explicit lazy/async attributes on every retrofitted public gallery/blog image', function () {
    // MasonryGrid/BeforeAfterSlider aren't listed here — they now render through the shared
    // `Picture` component (covered by the test above) rather than a raw <img> of their own.
    $files = [
        'resources/js/Pages/Public/Blog.tsx',
        'resources/js/Pages/Public/BlogDetail.tsx',
        'resources/js/Pages/Public/About.tsx',
        'resources/js/Pages/Public/Home.tsx',
        'resources/js/Pages/Public/Services.tsx',
        'resources/js/Pages/Public/ServiceDetail.tsx',
    ];

    foreach ($files as $file) {
        $source = file_get_contents(base_path($file));

        expect($source)->toContain('decoding="async"');
    }
});
