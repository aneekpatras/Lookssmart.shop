<?php

use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

$makeConfirmedUser = function (string $role): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

it('requires cms.manage to view the media library', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no cms.manage

    $this->actingAs($staff)->get('/admin/media')->assertForbidden();
});

it('automatically catalogs every image uploaded through SecureUploadService::storePublicImage', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $this->actingAs($admin)->post('/admin/media', [
        'files' => [
            UploadedFile::fake()->image('one.jpg', 800, 600),
            UploadedFile::fake()->image('two.png', 800, 600),
        ],
    ])->assertRedirect();

    expect(MediaAsset::count())->toBe(2);
    MediaAsset::all()->each(fn (MediaAsset $asset) => Storage::disk('public')->assertExists($asset->path));
});

it('scopes search and type filters correctly', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    MediaAsset::create(['disk' => 'public', 'path' => 'media/a.jpg', 'original_name' => 'sunset-photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);
    MediaAsset::create(['disk' => 'public', 'path' => 'media/b.png', 'original_name' => 'logo.png', 'mime_type' => 'image/png', 'size' => 2048]);

    $this->actingAs($admin)->get('/admin/media?search=sunset')->assertInertia(fn ($page) => $page
        ->where('assets.data', fn ($data) => count($data) === 1 && $data[0]['original_name'] === 'sunset-photo.jpg'));

    $this->actingAs($admin)->get('/admin/media?type=images')->assertInertia(fn ($page) => $page
        ->where('assets.data', fn ($data) => count($data) === 2));
});

it('detects when an asset is used by a blog post cover image', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'blog/cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);
    Post::factory()->create(['title' => 'Winter Tips', 'cover_image_path' => 'blog/cover.jpg']);

    $this->actingAs($admin)->get('/admin/media')->assertInertia(fn ($page) => $page
        ->where('assets.data.0.usages.0.label', 'Blog post cover: "Winter Tips"')
        ->where('assets.data.0.usages.0.clearable', true));
});

it('blocks deleting an in-use asset without confirmation', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Storage::disk('public')->put('blog/cover.jpg', 'fake-bytes');
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'blog/cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);
    Post::factory()->create(['cover_image_path' => 'blog/cover.jpg']);

    $response = $this->actingAs($admin)->deleteJson("/admin/media/{$asset->id}");

    $response->assertStatus(422);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
    Storage::disk('public')->assertExists('blog/cover.jpg');
});

it('force-deletes a clearable in-use asset and clears the referencing column', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Storage::disk('public')->put('blog/cover.jpg', 'fake-bytes');
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'blog/cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);
    $post = Post::factory()->create(['cover_image_path' => 'blog/cover.jpg']);

    $response = $this->actingAs($admin)->deleteJson("/admin/media/{$asset->id}?force=1");

    $response->assertOk();
    expect(MediaAsset::find($asset->id))->toBeNull()
        ->and($post->fresh()->cover_image_path)->toBeNull();
    Storage::disk('public')->assertMissing('blog/cover.jpg');
});

it('refuses to force-delete an asset required by a non-clearable reference', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();
    Storage::disk('public')->put('gallery/photo.jpg', 'fake-bytes');
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'gallery/photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);
    GalleryImage::factory()->create(['gallery_id' => $gallery->id, 'image_path' => 'gallery/photo.jpg']);

    $response = $this->actingAs($admin)->deleteJson("/admin/media/{$asset->id}?force=1");

    $response->assertStatus(409);
    expect(MediaAsset::find($asset->id))->not->toBeNull();
    Storage::disk('public')->assertExists('gallery/photo.jpg');
});

it('deletes an unused asset with no confirmation required', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Storage::disk('public')->put('media/unused.jpg', 'fake-bytes');
    $asset = MediaAsset::create(['disk' => 'public', 'path' => 'media/unused.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]);

    $this->actingAs($admin)->deleteJson("/admin/media/{$asset->id}")->assertOk();

    expect(MediaAsset::find($asset->id))->toBeNull();
    Storage::disk('public')->assertMissing('media/unused.jpg');
});
