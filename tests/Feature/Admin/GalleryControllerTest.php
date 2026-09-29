<?php

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
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

it('requires cms.manage to create an album', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no cms.manage

    $response = $this->actingAs($staff)->post('/admin/gallery', ['title' => 'Bridal Looks']);

    $response->assertForbidden();
    expect(Gallery::count())->toBe(0);
});

it('creates, updates, and deletes an album', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = GalleryCategory::factory()->create(['name' => 'Bridal']);

    $this->actingAs($admin)->post('/admin/gallery', [
        'title' => 'Bridal Looks',
        'gallery_category_id' => $category->id,
        'is_active' => true,
    ])->assertRedirect();

    $gallery = Gallery::where('title', 'Bridal Looks')->first();
    expect($gallery)->not->toBeNull()
        ->and($gallery->slug)->toBe('bridal-looks')
        ->and($gallery->gallery_category_id)->toBe($category->id);

    $this->actingAs($admin)->put("/admin/gallery/{$gallery->id}", [
        'title' => 'Bridal Looks 2026',
        'gallery_category_id' => $category->id,
        'is_active' => false,
    ])->assertRedirect();

    expect($gallery->fresh()->title)->toBe('Bridal Looks 2026')
        ->and($gallery->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->delete("/admin/gallery/{$gallery->id}")->assertRedirect();
    expect(Gallery::find($gallery->id))->toBeNull();
});

it('bulk uploads real images into an album', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/gallery/{$gallery->id}/images", [
        'images' => [
            UploadedFile::fake()->image('one.jpg', 800, 600),
            UploadedFile::fake()->image('two.jpg', 800, 600),
        ],
    ]);

    $response->assertRedirect();
    expect($gallery->images()->count())->toBe(2);

    $gallery->images->each(fn (GalleryImage $image) => Storage::disk('public')->assertExists($image->image_path));
});

it('rejects a php file renamed to .jpg in a bulk gallery upload', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/gallery/{$gallery->id}/images", [
        'images' => [UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>')],
    ]);

    $response->assertSessionHasErrors();
    expect($gallery->images()->count())->toBe(0);
});

it('marks an image as a before/after pair with a real uploaded after-image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();
    $image = GalleryImage::factory()->create(['gallery_id' => $gallery->id, 'is_before_after' => false]);
    $afterImage = UploadedFile::fake()->image('after.jpg', 800, 600);

    $response = $this->actingAs($admin)->put("/admin/gallery/{$gallery->id}/images/{$image->id}", [
        'caption' => 'Amazing transformation',
        'is_before_after' => true,
        'pair_image' => $afterImage,
    ]);

    $response->assertRedirect();
    $image->refresh();
    expect($image->is_before_after)->toBeTrue()
        ->and($image->caption)->toBe('Amazing transformation')
        ->and($image->pair_image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($image->pair_image_path);
});

it('sets a cover image scoped to the correct album', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();
    $otherGallery = Gallery::factory()->create();
    $image = GalleryImage::factory()->create(['gallery_id' => $gallery->id]);
    $foreignImage = GalleryImage::factory()->create(['gallery_id' => $otherGallery->id]);

    $this->actingAs($admin)->post("/admin/gallery/{$gallery->id}/cover", ['image_id' => $image->id])
        ->assertRedirect();
    expect($gallery->fresh()->cover_image_id)->toBe($image->id);

    $this->actingAs($admin)->post("/admin/gallery/{$gallery->id}/cover", ['image_id' => $foreignImage->id])
        ->assertStatus(422);
});

it('reorders images within an album', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();
    $first = GalleryImage::factory()->create(['gallery_id' => $gallery->id, 'sort' => 0]);
    $second = GalleryImage::factory()->create(['gallery_id' => $gallery->id, 'sort' => 1]);

    $this->actingAs($admin)->post("/admin/gallery/{$gallery->id}/images/reorder", [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->sort)->toBe(0)
        ->and($first->fresh()->sort)->toBe(1);
});

it('deletes an image and clears it as the cover if it was set', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $gallery = Gallery::factory()->create();
    $image = GalleryImage::factory()->create(['gallery_id' => $gallery->id, 'image_path' => 'gallery/cover.jpg']);
    Storage::disk('public')->put('gallery/cover.jpg', 'fake-bytes');
    $gallery->update(['cover_image_id' => $image->id]);

    $this->actingAs($admin)->delete("/admin/gallery/{$gallery->id}/images/{$image->id}")->assertRedirect();

    expect(GalleryImage::find($image->id))->toBeNull()
        ->and($gallery->fresh()->cover_image_id)->toBeNull();
    Storage::disk('public')->assertMissing('gallery/cover.jpg');
});
