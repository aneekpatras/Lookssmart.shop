<?php

use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\User;
use App\Support\FeaturedGalleryImages;
use App\Support\PublicImageScanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Mirrors the setup in tests/Feature/Admin/GalleryControllerTest.php. The gallery policies gate on
 * `cms.manage` (NOT a `gallery.*` permission), and the admin route group additionally requires a
 * verified email and confirmed 2FA — so the real seeded roles are used rather than a hand-built
 * permission, which silently 403s every request.
 */
function confirmedGalleryAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('admin');

    return $user;
}

/**
 * Covers the homepage bridal slider: which images it resolves, the public-folder fallback, the
 * admin "Show on Homepage Slider" toggle, and the ordering that makes the existing reorder arrows
 * double as slider priority.
 */
function bridalGallery(array $attributes = []): Gallery
{
    return Gallery::create([
        'title' => 'Looks Smart Brides',
        'slug' => 'brides-' . fake()->unique()->numberBetween(1, 100000),
        'sort' => 0,
        'is_active' => true,
        ...$attributes,
    ]);
}

function bridalImage(Gallery $gallery, array $attributes = []): GalleryImage
{
    return GalleryImage::create([
        'gallery_id' => $gallery->id,
        'image_path' => 'gallery/' . fake()->unique()->lexify('??????????') . '.jpg',
        'show_on_homepage' => true,
        'sort' => 0,
        ...$attributes,
    ]);
}

it('resolves only the images an admin enabled for the homepage', function () {
    $gallery = bridalGallery();
    $enabled = bridalImage($gallery, ['caption' => 'Walima bride']);
    bridalImage($gallery, ['show_on_homepage' => false]);

    $resolved = FeaturedGalleryImages::resolve();

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['id'])->toBe($enabled->id);
    expect($resolved[0]['source'])->toBe('gallery');
    expect($resolved[0]['alt'])->toBe('Walima bride');
});

it('drops homepage images whose album has been deactivated', function () {
    // Hiding an album everywhere else must not leave its photos on the busiest page of the site.
    $gallery = bridalGallery(['is_active' => false]);
    bridalImage($gallery);

    $resolved = FeaturedGalleryImages::resolve();

    expect(collect($resolved)->pluck('source')->unique()->all())->toBe(['public-folder']);
});

it('falls back to public/images when nothing is enabled, and never blends the two', function () {
    $gallery = bridalGallery();
    bridalImage($gallery, ['show_on_homepage' => false]);

    $resolved = FeaturedGalleryImages::resolve();

    // Every entry comes from the folder — a blend would make un-ticking look like a broken toggle.
    expect($resolved)->not->toBeEmpty();
    expect(collect($resolved)->pluck('source')->unique()->all())->toBe(['public-folder']);
});

it('percent-encodes spaces in public folder filenames', function () {
    $scanned = PublicImageScanner::bridalFallback();

    expect($scanned)->not->toBeEmpty();

    foreach ($scanned as $image) {
        expect($image['url'])->not->toContain(' ');
        expect($image['url'])->toStartWith('/images/');
        // The real assets are "bride 1.jpg" etc., so at least one must carry an encoded space.
        expect($image['alt'])->not->toBe('');
    }

    expect(collect($scanned)->pluck('url')->implode(' '))->toContain('%20');
});

it('excludes non-image files such as the hero video from the fallback', function () {
    // public/images/ also holds the homepage hero mp4; an allowlist keeps it out.
    foreach (PublicImageScanner::bridalFallback() as $image) {
        expect($image['url'])->not->toContain('.mp4');
    }
});

it('orders slider images by album sort then image sort, so the reorder arrows set priority', function () {
    $second = bridalGallery(['sort' => 2]);
    $first = bridalGallery(['sort' => 1]);

    $firstAlbumB = bridalImage($first, ['sort' => 1, 'caption' => 'first-album-b']);
    $firstAlbumA = bridalImage($first, ['sort' => 0, 'caption' => 'first-album-a']);
    $secondAlbumA = bridalImage($second, ['sort' => 0, 'caption' => 'second-album-a']);

    expect(collect(FeaturedGalleryImages::resolve())->pluck('id')->all())
        ->toBe([$firstAlbumA->id, $firstAlbumB->id, $secondAlbumA->id]);
});

it('exposes the slider images to the homepage as featured_gallery_images', function () {
    $gallery = bridalGallery();
    bridalImage($gallery, ['caption' => 'Barat bride']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Home')
            ->has('featured_gallery_images', 1)
            ->where('featured_gallery_images.0.alt', 'Barat bride')
            ->where('featured_gallery_images.0.source', 'gallery'),
        );
});

it('falls back to the album title for alt text when an image has no caption', function () {
    $gallery = bridalGallery(['title' => 'Bridal Portfolio 2026']);
    bridalImage($gallery, ['caption' => null]);

    // An empty alt would be skipped entirely by a screen reader.
    expect(FeaturedGalleryImages::resolve()[0]['alt'])->toBe('Bridal Portfolio 2026');
});

it('lets an admin toggle Show on Homepage Slider and reflects it immediately', function () {
    $gallery = bridalGallery();
    $image = bridalImage($gallery, ['show_on_homepage' => false, 'caption' => 'Mehndi bride']);

    $admin = confirmedGalleryAdmin();

    $this->actingAs($admin)
        ->put("/admin/gallery/{$gallery->id}/images/{$image->id}", ['show_on_homepage' => true])
        ->assertRedirect();

    expect($image->fresh()->show_on_homepage)->toBeTrue();

    // The homepage picks it up on the very next request — no cache to invalidate.
    $this->get('/')->assertInertia(fn ($page) => $page
        ->has('featured_gallery_images', 1)
        ->where('featured_gallery_images.0.alt', 'Mehndi bride'),
    );

    // And un-ticking returns the slider to the public-folder fallback.
    $this->actingAs($admin)
        ->put("/admin/gallery/{$gallery->id}/images/{$image->id}", ['show_on_homepage' => false])
        ->assertRedirect();

    expect($image->fresh()->show_on_homepage)->toBeFalse();
});

it('does not clobber the caption when only the homepage flag is sent', function () {
    // The admin toggle PUTs `show_on_homepage` alone; absent keys must stay untouched.
    $gallery = bridalGallery();
    $image = bridalImage($gallery, ['caption' => 'Keep me', 'is_before_after' => true]);

    $admin = confirmedGalleryAdmin();

    $this->actingAs($admin)
        ->put("/admin/gallery/{$gallery->id}/images/{$image->id}", ['show_on_homepage' => false]);

    $fresh = $image->fresh();
    expect($fresh->caption)->toBe('Keep me');
    expect($fresh->is_before_after)->toBeTrue();
    expect($fresh->show_on_homepage)->toBeFalse();
});
