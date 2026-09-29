<?php

use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a valid sitemap.xml including active services and published posts', function () {
    $category = ServiceCategory::factory()->create();
    $activeService = Service::factory()->create([
        'service_category_id' => $category->id,
        'slug' => 'signature-facial',
        'is_active' => true,
    ]);
    Service::factory()->create([
        'service_category_id' => $category->id,
        'slug' => 'retired-service',
        'is_active' => false,
    ]);

    $publishedPost = Post::factory()->create(['slug' => 'a-real-post', 'status' => 'published']);
    Post::factory()->create(['slug' => 'a-draft-post', 'status' => 'draft']);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    expect($xml->getName())->toBe('urlset');

    // A plain foreach is required here, not collect($xml->url) — SimpleXML's foreach magic walks
    // every sibling <url> node, but collect() on the same property only sees the last one.
    $locs = [];
    foreach ($xml->url as $urlNode) {
        $locs[] = (string) $urlNode->loc;
    }

    expect($locs)->toContain(url('/'))
        ->toContain(route('services.show', $activeService->slug))
        ->toContain(route('blog.show', $publishedPost->slug))
        ->not->toContain(route('services.show', 'retired-service'))
        ->not->toContain(route('blog.show', 'a-draft-post'));

    // /deals is now a real, controller-backed page (Deals & Offers task) and belongs in the sitemap.
    expect($locs)->toContain(route('deals.index'));
});

it('generates a robots.txt disallowing admin/private areas and pointing at the sitemap', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('User-agent: *')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /my-account')
        ->toContain('Sitemap: ' . route('sitemap'));
});

it('renders real LocalBusiness JSON-LD on the homepage from admin-editable settings', function () {
    Setting::create(['key' => 'business.name', 'value' => 'Looks Smart Test Salon', 'group' => 'business', 'is_encrypted' => false]);
    Setting::create(['key' => 'business.phone', 'value' => '+1-555-0100', 'group' => 'business', 'is_encrypted' => false]);
    Setting::create(['key' => 'business.address', 'value' => '123 Main St', 'group' => 'business', 'is_encrypted' => false]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Home')
        ->where('jsonLdSchema.@type', 'BeautySalon')
        ->where('jsonLdSchema.name', 'Looks Smart Test Salon')
        ->where('jsonLdSchema.telephone', '+1-555-0100')
        ->where('jsonLdSchema.address', '123 Main St')
        ->missing('jsonLdSchema.geo'));
});

it('includes geo coordinates in LocalBusiness JSON-LD only once both are configured', function () {
    Setting::create(['key' => 'business.latitude', 'value' => 40.7128, 'group' => 'business', 'is_encrypted' => false]);
    Setting::create(['key' => 'business.longitude', 'value' => -74.006, 'group' => 'business', 'is_encrypted' => false]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('jsonLdSchema.geo.@type', 'GeoCoordinates')
        ->where('jsonLdSchema.geo.latitude', 40.7128)
        ->where('jsonLdSchema.geo.longitude', -74.006));
});

it('renders real Service JSON-LD with the actual price and duration', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Hair']);
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'slug' => 'blowout',
        'name' => 'Signature Blowout',
        'base_price' => 65,
        'duration_min' => 45,
        'is_active' => true,
    ]);

    $response = $this->get("/services/{$service->slug}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/ServiceDetail')
        ->where('jsonLdSchema.@type', 'Service')
        ->where('jsonLdSchema.name', 'Signature Blowout')
        ->where('jsonLdSchema.serviceType', 'Hair')
        ->where('jsonLdSchema.offers.price', '65.00')
        ->where('jsonLdSchema.additionalProperty.value', '45 minutes'));
});

it('renders real BlogPosting JSON-LD with the actual author and dates', function () {
    $author = User::factory()->create(['name' => 'Jamie Rivera']);
    $post = Post::factory()->create([
        'slug' => 'seasonal-hair-care',
        'title' => 'Seasonal Hair Care Tips',
        'status' => 'published',
        'author_id' => $author->id,
    ]);

    $response = $this->get("/blog/{$post->slug}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/BlogDetail')
        ->where('jsonLdSchema.@type', 'BlogPosting')
        ->where('jsonLdSchema.headline', 'Seasonal Hair Care Tips')
        ->where('jsonLdSchema.author.name', 'Jamie Rivera')
        ->where('jsonLdSchema.datePublished', $post->published_at->toIso8601String()));
});
