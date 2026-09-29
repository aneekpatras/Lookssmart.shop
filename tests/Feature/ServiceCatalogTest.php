<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServiceGuide;
use Database\Seeders\SalonCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the 55-service catalog overhaul: the discounted price set, the category stock imagery and
 * its precedence against real uploaded media, the per-treatment-family detail-page guide, and the
 * `?service=` booking hand-off.
 */
it('seeds all 55 services across the 5 real categories', function () {
    $this->seed(SalonCatalogSeeder::class);

    expect(ServiceCategory::count())->toBe(5);
    expect(Service::count())->toBe(55);

    $counts = ServiceCategory::withCount('services')->orderBy('sort')->pluck('services_count', 'name');

    expect($counts['Hair Styling & Treatments'])->toBe(23);
    expect($counts['Skin & Facial Care'])->toBe(8);
    expect($counts['Bridal & Party Makeup'])->toBe(9);
    expect($counts['Eyelashes, Waxing & Threading'])->toBe(7);
    expect($counts['Massage, Spa & Special Services'])->toBe(8);
});

it('stores the salon\'s live discounted prices verbatim, with no further discount applied', function () {
    $this->seed(SalonCatalogSeeder::class);

    // Spot-checked across every category and across the whole price range, including the outliers
    // that were rounded to clean amounts rather than left at an exact 75% (11,600 / 1,850 / 19,100).
    $expected = [
        'HAIR-XTENSO-LOREAL' => 12000.0,
        'HAIR-EXTENSO-SIG' => 19500.0,
        'HAIR-REBOND-PERM' => 23250.0,
        'HAIR-EXTENSO-KERATIN' => 27000.0,
        'HAIR-BALAYAGE' => 11600.0,
        'HAIR-BABYLIGHTS' => 12350.0,
        'HAIR-EXT-PERMANENT' => 132000.0,
        'HAIR-CUT-KIDS' => 1850.0,
        'SKIN-HYDRAGLOW-GOLD' => 8250.0,
        'MUA-BARAT-JUNIOR' => 19100.0,
        'MUA-BARAT-SIGNATURE' => 60750.0,
        'THR-EYEBROW' => 150.0,
        'THR-FACE-FULL' => 600.0,
        'WAX-FACE-FULL' => 1125.0,
        'SPEC-MEHNDI-SINGLE' => 375.0,
        'SPEC-NAIL-GEL' => 3500.0,
    ];

    foreach ($expected as $sku => $price) {
        expect((float) Service::where('sku', $sku)->value('base_price'))
            ->toBe($price, "price for {$sku}");
    }
});

it('gives every service a real duration, price, description and unique slug', function () {
    $this->seed(SalonCatalogSeeder::class);

    expect(Service::where('duration_min', '<=', 0)->count())->toBe(0);
    expect(Service::where('base_price', '<=', 0)->count())->toBe(0);
    expect(Service::whereNull('description')->orWhere('description', '')->count())->toBe(0);
    expect(Service::distinct('slug')->count('slug'))->toBe(55);
    expect(Service::distinct('sku')->count('sku'))->toBe(55);
});

it('is idempotent — re-running the seeder refreshes rather than duplicates', function () {
    $this->seed(SalonCatalogSeeder::class);
    $this->seed(SalonCatalogSeeder::class);

    expect(Service::count())->toBe(55);
    expect(ServiceCategory::count())->toBe(5);
});

it('gives every category a stock image and falls back to it on the service card', function () {
    $this->seed(SalonCatalogSeeder::class);

    expect(ServiceCategory::whereNull('stock_image_url')->count())->toBe(0);

    $this->get('/services')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Services')
            ->has('services', 55)
            // No service has uploaded media, so every card falls back to its category's photo.
            ->where('services.0.image_url', fn (?string $url) => is_string($url)
                && str_starts_with($url, 'https://images.unsplash.com/')),
        );
});

it('allows any https image host in the CSP so admin-pasted stock imagery is not silently blocked', function () {
    $csp = $this->get('/services')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("img-src 'self' data: https:")
        ->not->toContain('unsafe-inline');
});

it('resolves a complete treatment guide for every single service', function () {
    $this->seed(SalonCatalogSeeder::class);

    Service::with('category')->get()->each(function (Service $service) {
        $guide = ServiceGuide::for($service);

        expect($guide['procedure'])->not->toBeEmpty("procedure for {$service->sku}");
        expect($guide['benefits'])->not->toBeEmpty("benefits for {$service->sku}");
        expect($guide['aftercare'])->not->toBeEmpty("aftercare for {$service->sku}");
        expect($guide['suitability'])->toBeString()->not->toBeEmpty("suitability for {$service->sku}");

        foreach ($guide['procedure'] as $step) {
            expect($step)->toHaveKeys(['title', 'detail']);
        }
    });
});

it('matches each service to the guidance for its actual treatment, not just its category', function () {
    $this->seed(SalonCatalogSeeder::class);

    // Chemical smoothing services must carry the 72-hour no-wash rule the task called out.
    // NOTE: `toContain()` is variadic — every extra argument is another needle, not a failure
    // message, so the SKU is surfaced by collecting misses instead.
    $missing = collect(['HAIR-XTENSO-LOREAL', 'HAIR-REBOND-PERM', 'HAIR-EXTENSO-KERATIN'])
        ->reject(fn (string $sku) => str_contains(
            implode(' ', ServiceGuide::for(Service::where('sku', $sku)->first())['aftercare']),
            '72 hours',
        ));

    expect($missing->all())->toBe([]);

    // Short Hair Keratin Smoothing sits in the Spa/Specials CATEGORY per the salon's own menu, but
    // is chemically a smoothing treatment — it must still get the smoothing aftercare, which is the
    // whole reason the guide keys on treatment family rather than category.
    $keratin = Service::where('sku', 'HAIR-KERATIN-SHORT')->with('category')->first();
    expect($keratin->category->name)->toBe('Massage, Spa & Special Services');
    expect(implode(' ', ServiceGuide::for($keratin)['aftercare']))->toContain('72 hours');

    // Facials must carry sun protection, the task's other named example.
    $facial = implode(' ', ServiceGuide::for(Service::where('sku', 'SKIN-GOLD-HYDRA')->first())['aftercare']);
    expect($facial)->toContain('SPF');

    // And a haircut must NOT inherit facial or chemical guidance.
    $cut = implode(' ', ServiceGuide::for(Service::where('sku', 'HAIR-CUT-JUNIOR')->first())['aftercare']);
    expect($cut)->not->toContain('SPF')->not->toContain('72 hours');
});

it('serves the detail page with a full guide for a service from every category', function () {
    $this->seed(SalonCatalogSeeder::class);

    $slugs = Service::with('category')
        ->get()
        ->groupBy(fn (Service $service) => $service->category->slug)
        ->map(fn ($group) => $group->first()->slug);

    expect($slugs)->toHaveCount(5);

    foreach ($slugs as $slug) {
        $this->get("/services/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/ServiceDetail')
                ->has('service.description')
                ->has('guide.procedure')
                ->has('guide.benefits')
                ->has('guide.aftercare')
                ->has('guide.suitability'),
            );
    }
});

it('exposes the service id the booking wizard preselects from', function () {
    $this->seed(SalonCatalogSeeder::class);

    $service = Service::where('sku', 'HAIR-BALAYAGE')->first();

    // The card links to /book?service=<id>; the wizard validates that id against this same list.
    $this->get('/book')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Book')
            ->has('services', 55)
            ->where('services', fn ($services) => collect($services)->contains('id', $service->id)),
        );
});

it('keeps related services on a detail page inside the same category', function () {
    $this->seed(SalonCatalogSeeder::class);

    $service = Service::where('sku', 'SKIN-GOLD-HYDRA')->first();

    $this->get("/services/{$service->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('relatedServices', fn ($related) => collect($related)->isNotEmpty()
                && collect($related)->every(fn ($item) => $item['category'] === 'Skin & Facial Care')
                && collect($related)->doesntContain('id', $service->id)),
        );
});
