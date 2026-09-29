<?php

use App\Models\BusinessHour;
use App\Models\Deal;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\Lead;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('renders the public homepage with featured active services', function () {
    $category = ServiceCategory::factory()->create(['name' => 'Hair']);
    $featured = Service::factory()->create([
        'service_category_id' => $category->id,
        'name' => 'Signature Cut',
        'slug' => 'signature-cut',
        'is_featured' => true,
        'is_active' => true,
    ]);
    Service::factory()->create([
        'service_category_id' => $category->id,
        'is_featured' => true,
        'is_active' => false,
    ]);

    $response = $this->get('/');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Home')
        ->where('featuredServices.0.id', $featured->id)
        ->where('featuredServices.0.name', 'Signature Cut')
        ->where('categories.0.name', 'Hair'));
});

it('renders active services with search and category filtering', function () {
    $hair = ServiceCategory::factory()->create(['name' => 'Hair']);
    $skin = ServiceCategory::factory()->create(['name' => 'Skin']);
    $matching = Service::factory()->create([
        'service_category_id' => $hair->id,
        'name' => 'Silk Blowout',
        'slug' => 'silk-blowout',
        'is_active' => true,
    ]);
    Service::factory()->create(['service_category_id' => $hair->id, 'name' => 'Hair Trim', 'is_active' => true]);
    Service::factory()->create(['service_category_id' => $skin->id, 'name' => 'Silk Facial', 'is_active' => true]);
    Service::factory()->create(['service_category_id' => $hair->id, 'name' => 'Silk Archive', 'is_active' => false]);

    $response = $this->get('/services?search=blowout&category=' . $hair->id);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Services')
        ->where('services', fn ($services) => count($services) === 1 && $services[0]['id'] === $matching->id)
        ->where('filters.search', 'blowout')
        ->where('filters.category', $hair->id));
});

it('renders an active service detail page by slug', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'name' => 'Polished Nails',
        'slug' => 'polished-nails',
        'is_active' => true,
    ]);

    $this->get('/services/polished-nails')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/ServiceDetail')
        ->where('service.id', $service->id)
        ->where('service.slug', 'polished-nails'));
});

it('shows approved reviews and active offers on public pages only', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'is_active' => true]);
    $customer = User::factory()->create();
    Review::factory()->create(['service_id' => $service->id, 'customer_id' => $customer->id, 'rating' => 5, 'status' => 'approved', 'published_at' => now()]);
    Review::factory()->create(['service_id' => $service->id, 'customer_id' => $customer->id, 'rating' => 1, 'status' => 'pending', 'published_at' => now()]);
    $deal = Deal::factory()->create(['title' => 'Spring glow', 'code' => 'GLOW10', 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    Deal::factory()->create(['is_active' => false, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('offers.0.id', $deal->id)
        ->where('testimonials.0.rating', 5));
    $this->get('/services/' . $service->slug)->assertInertia(fn ($page) => $page
        ->where('service.rating', 5)
        ->where('service.review_count', 1)
        ->where('testimonials.0.rating', 5));
});

it('validates an active promo code through the booking quote endpoint', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 100, 'is_active' => true]);
    Deal::factory()->create(['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    $this->postJson('/api/booking/quote', ['service_ids' => [$service->id], 'code' => 'save20'])
        ->assertOk()->assertJsonPath('discount', 20);
    $this->postJson('/api/booking/quote', ['service_ids' => [$service->id], 'code' => 'NOTREAL'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('renders the public gallery page as category sections, active galleries only', function () {
    $category = GalleryCategory::factory()->create(['name' => 'Hair Treatments', 'sort' => 0]);
    $gallery1 = Gallery::factory()->create(['title' => 'Hair Styling', 'is_active' => true, 'gallery_category_id' => $category->id]);
    $gallery2 = Gallery::factory()->create(['title' => 'Makeup', 'is_active' => false, 'gallery_category_id' => $category->id]);
    GalleryImage::factory()->count(3)->create(['gallery_id' => $gallery1->id]);
    GalleryImage::factory()->count(2)->create(['gallery_id' => $gallery2->id]);

    $response = $this->get('/gallery');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Gallery')
        ->where('sections.0.id', $category->id)
        ->where('sections.0.name', 'Hair Treatments')
        ->where('sections.0.images', fn ($images) => count($images) === 3));
});

it('renders gallery category sections strictly in the admin-defined sort order', function () {
    $second = GalleryCategory::factory()->create(['name' => 'Facials & Glow', 'sort' => 1]);
    $first = GalleryCategory::factory()->create(['name' => 'Looks Smart Brides', 'sort' => 0]);
    GalleryImage::factory()->create(['gallery_id' => Gallery::factory()->create(['gallery_category_id' => $first->id])->id]);
    GalleryImage::factory()->create(['gallery_id' => Gallery::factory()->create(['gallery_category_id' => $second->id])->id]);

    $response = $this->get('/gallery');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('sections.0.name', 'Looks Smart Brides')
        ->where('sections.1.name', 'Facials & Glow'));
});

it('does not render a category section with zero active images', function () {
    GalleryCategory::factory()->create(['name' => 'Empty Category']);

    $response = $this->get('/gallery');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('sections', []));
});

it('keeps an active album\'s images visible under "More Highlights" when it has no category assigned', function () {
    $gallery = Gallery::factory()->create(['is_active' => true, 'gallery_category_id' => null]);
    GalleryImage::factory()->count(2)->create(['gallery_id' => $gallery->id]);

    $response = $this->get('/gallery');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('sections.0.id', 'uncategorized')
        ->where('sections.0.name', 'More Highlights')
        ->where('sections.0.images', fn ($images) => count($images) === 2));
});

it('filters gallery images by before/after status', function () {
    $category = GalleryCategory::factory()->create();
    $gallery = Gallery::factory()->create(['gallery_category_id' => $category->id]);
    GalleryImage::factory()->count(2)->create(['gallery_id' => $gallery->id, 'is_before_after' => false]);
    GalleryImage::factory()->beforeAfter()->count(1)->create(['gallery_id' => $gallery->id]);

    $response = $this->get('/gallery');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('sections.0.images', fn ($images) => count($images) === 3 && collect($images)->where('is_before_after', true)->count() === 1));
});

it('includes captions and image paths in gallery payload', function () {
    $category = GalleryCategory::factory()->create();
    $gallery = Gallery::factory()->create(['gallery_category_id' => $category->id]);
    GalleryImage::factory()->create([
        'gallery_id' => $gallery->id,
        'image_path' => 'gallery/test-image.jpg',
        'caption' => 'Beautiful transformation',
    ]);

    $response = $this->get('/gallery');

    $response->assertInertia(fn ($page) => $page
        ->where('sections.0.images.0.caption', 'Beautiful transformation')
        ->where('sections.0.images.0.image_path', fn ($path) => str_contains($path, 'gallery/test-image.jpg')));
});

/*
 * The blog index now pulls the newest published post out into a separate `featuredPost` prop for
 * the hero banner, so on an unfiltered first page `posts` no longer includes it — a real contract
 * change, which is why this test now seeds two published posts and asserts each prop separately
 * rather than asserting a flat `posts` count of 1.
 */
it('renders the blog index with the newest post featured and drafts/future posts excluded', function () {
    $category = PostCategory::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
    $older = Post::factory()->create([
        'post_category_id' => $category->id,
        'title' => 'Older Hair Care Tips',
        'status' => 'published',
        'published_at' => now()->subDays(3),
    ]);
    $newest = Post::factory()->create([
        'post_category_id' => $category->id,
        'title' => 'Winter Hair Care Tips',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Post::factory()->create([
        'post_category_id' => $category->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
    Post::factory()->create([
        'post_category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->addDay(),
    ]);

    $response = $this->get('/blog');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Blog')
        ->where('featuredPost.id', $newest->id)
        ->where('posts', fn ($posts) => count($posts) === 1 && $posts[0]['id'] === $older->id));
});

it('does not feature a post once the visitor has searched or filtered', function () {
    $category = PostCategory::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
    Post::factory()->create([
        'post_category_id' => $category->id,
        'title' => 'Winter Hair Care Tips',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/blog?search=winter')
        ->assertInertia(fn ($page) => $page->where('featuredPost', null)->has('posts', 1));

    $this->get("/blog?category={$category->id}")
        ->assertInertia(fn ($page) => $page->where('featuredPost', null)->has('posts', 1));
});

it('renders a published blog post by slug with reading time and TOC', function () {
    $category = PostCategory::create(['name' => 'Skin Care', 'slug' => 'skin-care']);
    $post = Post::factory()->create([
        'post_category_id' => $category->id,
        'slug' => 'glowing-skin-routine',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'body' => '<h2>Intro</h2><p>Content.</p><h2>Steps</h2><p>More content.</p>',
    ]);

    $response = $this->get('/blog/glowing-skin-routine');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/BlogDetail')
        ->where('post.id', $post->id)
        ->where('post.slug', 'glowing-skin-routine')
        ->where('readingTimeMinutes', fn ($minutes) => $minutes >= 1)
        ->where('tableOfContents', fn ($toc) => count($toc) === 2));
});

it('returns a 404 for a draft or scheduled post slug', function () {
    $category = PostCategory::create(['name' => 'Nails', 'slug' => 'nails']);
    Post::factory()->create([
        'post_category_id' => $category->id,
        'slug' => 'unpublished-draft',
        'status' => 'draft',
        'published_at' => null,
    ]);
    Post::factory()->create([
        'post_category_id' => $category->id,
        'slug' => 'future-post',
        'status' => 'published',
        'published_at' => now()->addDay(),
    ]);

    $this->get('/blog/unpublished-draft')->assertNotFound();
    $this->get('/blog/future-post')->assertNotFound();
});

/*
 * The contact form's field contract changed with the Contact page rebuild: `phone` is now required
 * outright (the salon replies over WhatsApp, and the old `required_without:email` pairing allowed
 * an email-only lead nobody could answer quickly), `email` is genuinely optional, and the
 * `service_interest` select was replaced by a free-text `subject`. This payload and the assertions
 * below follow that new contract — a changed contract, not a loosened assertion.
 */
function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jamie Rivera',
        'phone' => '0300 1234567',
        'email' => 'jamie@example.com',
        'subject' => 'Bridal enquiry',
        'message' => 'I would like to book a consultation for next week.',
        'website' => '',
        'rendered_at' => now()->subSeconds(5)->timestamp,
    ], $overrides);
}

it('renders the contact page with business info, hours and latest posts', function () {
    $response = $this->get('/contact');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/Contact')
        ->has('business')
        ->has('businessHours')
        ->has('latestPosts'));
});

it('creates a lead from a valid contact form submission', function () {
    $response = $this->post('/contact', contactPayload());

    $response->assertRedirect()->assertSessionHas('success');

    expect(Lead::count())->toBe(1);
    $lead = Lead::first();
    expect($lead->name)->toBe('Jamie Rivera')
        ->and($lead->email)->toBe('jamie@example.com')
        ->and($lead->phone)->toBe('0300 1234567')
        ->and($lead->source)->toBe('contact_form')
        ->and($lead->status)->toBe('new')
        // `leads` has no subject column, so it is prefixed onto the notes body.
        ->and($lead->notes)->toContain('Bridal enquiry')
        ->and($lead->notes)->toContain('consultation');
});

it('requires a phone number so every lead is reachable on WhatsApp', function () {
    $response = $this->post('/contact', contactPayload(['phone' => null]));

    $response->assertSessionHasErrors('phone');
    expect(Lead::count())->toBe(0);
});

it('accepts a submission with no email address at all', function () {
    $response = $this->post('/contact', contactPayload(['email' => null, 'subject' => null]));

    $response->assertRedirect()->assertSessionHas('success');

    $lead = Lead::sole();
    expect($lead->email)->toBeNull()
        ->and($lead->phone)->toBe('0300 1234567')
        // With no subject there should be no stray "Subject:" prefix left on the notes.
        ->and($lead->notes)->not->toContain('Subject:');
});

it('silently blocks a contact submission that fails the honeypot check', function () {
    $response = $this->post('/contact', contactPayload(['website' => 'https://spam.example']));

    $response->assertRedirect();
    expect(Lead::count())->toBe(0);
});

it('silently blocks a contact submission that fails the time-trap check', function () {
    $response = $this->post('/contact', contactPayload(['rendered_at' => now()->timestamp]));

    $response->assertRedirect();
    expect(Lead::count())->toBe(0);
});

it('rate limits repeated contact form submissions from the same IP', function () {
    RateLimiter::clear('contact:127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        $this->post('/contact', contactPayload(['email' => "jamie{$i}@example.com"]))->assertRedirect();
    }

    $this->post('/contact', contactPayload(['email' => 'jamie6@example.com']))->assertStatus(429);

    expect(Lead::count())->toBe(5);
});

/*
 * The previous version of this test asserted `team`, `stats`, `timeline` and `values`. All four
 * props were removed when the About page was rebuilt to the salon's specified 7-section structure:
 * the team roster was publishing factory-generated staff names on the live site, and the
 * live-computed stat band contradicted the editorial quick-stat row the brief specifies. This is
 * supersession of a changed contract, not a loosened assertion — the new payload is asserted in
 * full below, and the page's rendered output is covered by tests/e2e/about-page.spec.ts.
 */
it('renders the about page with the quick stats, pillars, founder and contact payload', function () {
    Setting::create(['key' => 'business.address', 'value' => 'Central Park Housing Scheme, Lahore', 'group' => 'business']);
    Setting::create(['key' => 'business.phone', 'value' => '+92 305 9833859', 'group' => 'business']);

    foreach (range(0, 6) as $weekday) {
        BusinessHour::create([
            'weekday' => $weekday,
            'open_time' => '10:00',
            'close_time' => '21:00',
            'is_closed' => false,
        ]);
    }

    $this->get('/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/About')
            ->has('quickStats', 4)
            ->where('quickStats.0.value', '10+')
            ->where('quickStats.3.value', 'Lahore')
            ->has('pillars', 4)
            ->where('pillars.0.title', 'Care')
            ->where('pillars.3.title', 'Artistry')
            ->where('founder.name', 'Dua')
            ->where('founder.role', 'Founder & Creative Director')
            // No photograph of the founder exists; the page must not borrow someone else's.
            ->where('founder.photo_url', null)
            ->where('contact.address', 'Central Park Housing Scheme, Lahore')
            ->where('contact.whatsapp_url', 'https://wa.me/923059833859')
            ->has('contact.hours', 7));
});
