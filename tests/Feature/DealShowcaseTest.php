<?php

use App\Models\Deal;
use App\Models\Service;
use App\Services\PriceQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * `DealFactory`'s default `ends_at` is a random point across a ~90-day window that can legitimately
 * land in the past, so any test relying on `Deal::active()` returning a given deal must pin a
 * definitely-active window explicitly rather than trust the factory default.
 *
 * @return array{starts_at: Carbon, ends_at: Carbon}
 */
function activeWindow(): array
{
    return ['starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3)];
}

it('renders the public deals page with one section per category tag that has an active deal', function () {
    $serviceA = Service::factory()->create(['base_price' => 5000]);
    $serviceB = Service::factory()->create(['base_price' => 4000]);

    $hairDeal = Deal::factory()->create([
        ...activeWindow(),
        'title' => 'Hair Revival Bundle',
        'category_tag' => 'Hair Deals',
        'type' => 'fixed',
        'value' => 3000,
        'original_price' => 9000,
        'deal_price' => 6000,
        'included_services' => ['Deep conditioning', 'Blow dry'],
        'code' => 'HAIRVOUCHER',
        'is_active' => true,
        'is_top_deal' => false,
    ]);
    $hairDeal->services()->sync([$serviceA->id, $serviceB->id]);

    $skinDeal = Deal::factory()->create([
        ...activeWindow(),
        'title' => 'Glow Facial Package',
        'category_tag' => 'Skin Deals',
        'is_active' => true,
        'is_top_deal' => true,
        'original_price' => 8000,
        'deal_price' => 5000,
    ]);

    // Not shown: inactive, and no category tag at all (an old discount-engine-only deal).
    Deal::factory()->create([...activeWindow(), 'category_tag' => 'Combo Deals', 'is_active' => false]);
    Deal::factory()->create([...activeWindow(), 'category_tag' => null, 'is_active' => true]);

    $response = $this->get('/deals');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Deals')
        ->has('sections')
        ->where('availableTags', fn ($tags) => collect($tags)->contains('Hair Deals')
            && collect($tags)->contains('Skin Deals')
            && collect($tags)->contains('Top Deals') // is_top_deal pin surfaces a dedicated Top Deals section.
            && ! collect($tags)->contains('Combo Deals')));
});

it('surfaces a deal in Top Deals via either its own tag or the is_top_deal pin, without duplicating a Hair Deals pin', function () {
    $pinned = Deal::factory()->create([
        ...activeWindow(),
        'title' => 'Pinned Skin Deal',
        'category_tag' => 'Skin Deals',
        'is_top_deal' => true,
        'is_active' => true,
    ]);
    $taggedTop = Deal::factory()->create([
        ...activeWindow(),
        'title' => 'Native Top Deal',
        'category_tag' => 'Top Deals',
        'is_top_deal' => false,
        'is_active' => true,
    ]);

    $response = $this->get('/deals');
    $sections = $response->viewData('page')['props']['sections'];

    $topSection = collect($sections)->firstWhere('tag', 'Top Deals');
    $skinSection = collect($sections)->firstWhere('tag', 'Skin Deals');

    expect($topSection)->not->toBeNull();
    $topTitles = collect($topSection['deals'])->pluck('title')->all();
    expect($topTitles)->toContain('Pinned Skin Deal')->toContain('Native Top Deal');

    // Still shown once in its real Skin Deals section too — the pin does not remove it from there.
    expect($skinSection)->not->toBeNull();
    expect(collect($skinSection['deals'])->pluck('title')->all())->toContain('Pinned Skin Deal');
});

it('computes savings_percent from live prices and omits it when a price is missing', function () {
    $withPrices = Deal::factory()->create(['original_price' => 10000, 'deal_price' => 7500]);
    $withoutOriginal = Deal::factory()->create(['original_price' => null, 'deal_price' => 5000]);

    expect($withPrices->fresh()->savings_percent)->toBe(25)
        ->and($withoutOriginal->fresh()->savings_percent)->toBeNull();
});

it('builds claim_url with the deal code when present, and a plain /book link when not', function () {
    $withCode = Deal::factory()->create([...activeWindow(), 'category_tag' => 'Hair Deals', 'code' => 'SAVE20', 'is_active' => true]);
    $withoutCode = Deal::factory()->create([...activeWindow(), 'category_tag' => 'Skin Deals', 'code' => null, 'is_active' => true]);

    $response = $this->get('/deals');
    $sections = $response->viewData('page')['props']['sections'];

    $hairDeal = collect($sections)->firstWhere('tag', 'Hair Deals')['deals'][0];
    $skinDeal = collect($sections)->firstWhere('tag', 'Skin Deals')['deals'][0];

    expect($hairDeal['claim_url'])->toBe('/book?code=SAVE20')
        ->and($skinDeal['claim_url'])->toBe('/book');

    expect($withCode->code)->toBe('SAVE20')->and($withoutCode->code)->toBeNull();
});

it('resolves an external Unsplash-style URL in image_path as-is, but a local path through asset(storage/...)', function () {
    $external = Deal::factory()->create([
        ...activeWindow(),
        'category_tag' => 'Hair Deals',
        'image_path' => 'https://images.unsplash.com/photo-example',
        'is_active' => true,
    ]);
    $local = Deal::factory()->create([
        ...activeWindow(),
        'category_tag' => 'Skin Deals',
        'image_path' => 'deals/local-photo.webp',
        'is_active' => true,
    ]);

    $response = $this->get('/deals');
    $sections = $response->viewData('page')['props']['sections'];

    $hairDeal = collect($sections)->firstWhere('tag', 'Hair Deals')['deals'][0];
    $skinDeal = collect($sections)->firstWhere('tag', 'Skin Deals')['deals'][0];

    expect($hairDeal['image_url'])->toBe('https://images.unsplash.com/photo-example')
        ->and($skinDeal['image_url'])->toBe(asset('storage/deals/local-photo.webp'));
});

it('builds claim_url with every attached service pre-selected, so Claim Offer lands on /book with the deal already in the cart', function () {
    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();
    $deal = Deal::factory()->create([
        ...activeWindow(),
        'category_tag' => 'Hair Deals',
        'code' => 'BUNDLE10',
        'is_active' => true,
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    $response = $this->get('/deals');
    $sections = $response->viewData('page')['props']['sections'];
    $hairDeal = collect($sections)->firstWhere('tag', 'Hair Deals')['deals'][0];

    expect($hairDeal['claim_url'])
        ->toContain("service={$serviceA->id}")
        ->toContain("service={$serviceB->id}")
        ->toContain('code=BUNDLE10');

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('offers.0.claim_url', fn (string $url) => str_contains($url, "service={$serviceA->id}")
            && str_contains($url, "service={$serviceB->id}")
            && str_contains($url, 'code=BUNDLE10')));
});

it('falls back to the real attached services list when a deal has no marketing included_services copy', function () {
    $service = Service::factory()->create(['name' => 'Signature Cut']);
    $deal = Deal::factory()->create([
        ...activeWindow(),
        'category_tag' => 'Hair Deals',
        'included_services' => null,
        'is_active' => true,
    ]);
    $deal->services()->sync([$service->id]);

    $response = $this->get('/deals');
    $sections = $response->viewData('page')['props']['sections'];
    $hairDeal = collect($sections)->firstWhere('tag', 'Hair Deals')['deals'][0];

    expect($hairDeal['included_services'])->toContain('Signature Cut');
});

it('produces a real booking quote that exactly matches the advertised deal_price for its service bundle', function () {
    $serviceA = Service::factory()->create(['base_price' => 8250]);
    $serviceB = Service::factory()->create(['base_price' => 6000]);

    $deal = Deal::factory()->create([
        'title' => 'Hair Revival Bundle',
        'category_tag' => 'Hair Deals',
        'type' => 'fixed',
        'value' => 3750, // 14250 - 10500
        'original_price' => 14250,
        'deal_price' => 10500,
        'code' => 'HAIRVOUCHER',
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonths(3),
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    $quote = app(PriceQuoteService::class)->quote([$serviceA->id, $serviceB->id], 'HAIRVOUCHER');

    expect($quote['subtotal'])->toBe(14250.0)
        ->and($quote['discount'])->toBe(3750.0)
        ->and($quote['total'])->toBe((float) $deal->deal_price);
});
