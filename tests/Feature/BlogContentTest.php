<?php

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\BlogContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the blog content overhaul: the real seeded articles (replacing 15 factory posts with no
 * category, no tags, no headings and no cover image), the sidebar data (trending, quick service
 * links, WhatsApp), the featured-post extraction, and the cover-image precedence between a real
 * admin upload and the seeded external URL.
 */
it('seeds 7 real categories, 7 tags, and 8 genuine articles', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    expect(PostCategory::count())->toBe(7);
    expect(Tag::count())->toBe(7);
    expect(Post::count())->toBe(8);

    // Every post is genuinely published, categorised and tagged — the gaps the factory left.
    expect(Post::where('status', '!=', 'published')->count())->toBe(0);
    expect(Post::whereNull('post_category_id')->count())->toBe(0);
    expect(Post::doesntHave('tags')->count())->toBe(0);
});

it('gives the flagship laser article real heading structure and the named local image', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    $post = Post::where('slug', 'laser-hair-removal-in-lahore-your-questions-answered-honestly')->firstOrFail();

    expect($post->title)->toBe('Laser Hair Removal in Lahore: Your Questions Answered Honestly');
    expect(substr_count($post->body, '<h2>'))->toBeGreaterThanOrEqual(5);
    expect($post->body)->toContain('<blockquote>');
    expect($post->cover_image_url)->toContain('Blog');
    expect($post->cover_image_url)->toContain('.avif');

    // Named H2 sections from the task spec.
    foreach ([
        'Does Laser Hair Removal Work on Pakistani Skin?',
        'Is Laser Hair Removal Painful?',
        'How Many Sessions Do You Need?',
        'Is It Safe?',
        'What Should You Avoid Before and After Each Session?',
        'How to Book Laser Hair Removal at Looks Smart Lahore',
    ] as $heading) {
        expect($post->body)->toContain($heading);
    }
});

it('is idempotent — re-running the seeder refreshes rather than duplicates', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);
    $this->seed(BlogContentSeeder::class);

    expect(Post::count())->toBe(8);
    expect(PostCategory::count())->toBe(7);
    expect(Tag::count())->toBe(7);
});

it('extracts the newest post as featured and excludes it from the grid', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Blog')
            ->has('featuredPost')
            ->where('featuredPost.slug', 'laser-hair-removal-in-lahore-your-questions-answered-honestly')
            ->where('posts', fn ($posts) => collect($posts)->doesntContain(
                'slug',
                'laser-hair-removal-in-lahore-your-questions-answered-honestly',
            )),
        );
});

it('passes trending posts, quick service links and a WhatsApp link to the blog index', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);
    ServiceCategory::factory()->count(5)->create(['is_active' => true]);

    $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trendingPosts', 5)
            ->has('quickServiceLinks', 5)
            ->where('whatsapp.chat_url', 'https://wa.me/923059833859'),
        );
});

it('passes location and WhatsApp details to the article sidebar', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);
    Setting::create(['key' => 'business.address', 'value' => 'Central Park Housing Scheme, Lahore', 'group' => 'business']);

    $this->get('/blog/laser-hair-removal-in-lahore-your-questions-answered-honestly')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/BlogDetail')
            ->where('whatsapp.chat_url', 'https://wa.me/923059833859')
            ->where('location.address', fn (?string $address) => str_contains((string) $address, 'Central Park')),
        );
});

it('prefers a real admin-uploaded cover over the seeded external URL', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    $post = Post::where('slug', 'laser-hair-removal-in-lahore-your-questions-answered-honestly')->firstOrFail();
    expect($post->cover_image_url)->not->toBeNull();

    // Simulate a real admin upload landing on the same post.
    $post->update(['cover_image_path' => 'blog/genuine-upload.jpg']);

    $this->get('/blog')
        ->assertInertia(fn ($page) => $page
            ->where('featuredPost.cover_image_path', fn (string $url) => str_contains($url, 'storage/blog/genuine-upload.jpg')),
        );
});

it('tops up related articles with other recent posts when the category alone has too few', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    // "Laser" has exactly one published article (the flagship one) — same-category alone would
    // return zero related posts, leaving the spec's "2-3 recommended posts" grid empty.
    $this->get('/blog/laser-hair-removal-in-lahore-your-questions-answered-honestly')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('relatedPosts', 3)
            ->where('relatedPosts', fn ($related) => collect($related)->doesntContain(
                'slug',
                'laser-hair-removal-in-lahore-your-questions-answered-honestly',
            )),
        );
});

it('returns an absolute URL for the seeded local cover image, not a bare root-relative path', function () {
    User::factory()->create();
    $this->seed(BlogContentSeeder::class);

    $response = $this->get('/blog/laser-hair-removal-in-lahore-your-questions-answered-honestly');

    $response->assertInertia(fn ($page) => $page
        ->where('post.cover_image_path', fn (string $url) => str_starts_with($url, 'http')),
    );
});
