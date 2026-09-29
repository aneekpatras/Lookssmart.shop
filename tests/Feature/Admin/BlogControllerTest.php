<?php

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
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

it('requires cms.manage to create a post', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no cms.manage

    $response = $this->actingAs($staff)->post('/admin/blog', [
        'title' => 'Winter Hair Care',
        'body' => '<p>Content</p>',
        'status' => 'draft',
    ]);

    $response->assertForbidden();
    expect(Post::count())->toBe(0);
});

it('creates a published post with sanitized TipTap HTML, tags, and a real cover image upload', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = PostCategory::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
    $image = UploadedFile::fake()->image('cover.jpg', 1200, 630);

    $response = $this->actingAs($admin)->post('/admin/blog', [
        'title' => 'Winter Hair Care Tips',
        'excerpt' => 'Keep your hair healthy this winter.',
        'body' => '<h2>Intro</h2><p>Real content.</p><script>alert(1)</script>',
        'post_category_id' => $category->id,
        'tags' => 'hair, winter, tips',
        'seo_title' => 'Winter Hair Care Tips | Looks Smart',
        'seo_description' => 'Our top tips for winter hair care.',
        'status' => 'published',
        'cover_image' => $image,
    ]);

    $response->assertRedirect();
    $post = Post::where('title', 'Winter Hair Care Tips')->first();

    expect($post)->not->toBeNull()
        ->and($post->slug)->toBe('winter-hair-care-tips')
        ->and($post->status)->toBe('published')
        ->and($post->published_at)->not->toBeNull()
        ->and($post->body)->toContain('Real content.')
        ->and($post->body)->not->toContain('<script>')
        ->and($post->author_id)->toBe($admin->id)
        ->and($post->cover_image_path)->not->toBeNull()
        ->and($post->tags()->pluck('name')->sort()->values()->all())->toBe(['hair', 'tips', 'winter']);

    Storage::disk('public')->assertExists($post->cover_image_path);
});

it('rejects a php file renamed to .jpg for a post cover image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $fakePhp = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>');

    $response = $this->actingAs($admin)->post('/admin/blog', [
        'title' => 'Bad Upload',
        'body' => '<p>Content</p>',
        'status' => 'draft',
        'cover_image' => $fakePhp,
    ]);

    $response->assertSessionHasErrors('file');
    expect(Post::count())->toBe(0);
});

it('keeps a scheduled post unpublished until its scheduled time passes, then promotes it', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $response = $this->actingAs($admin)->post('/admin/blog', [
        'title' => 'Spring Collection Launch',
        'body' => '<p>Coming soon.</p>',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ]);

    $response->assertRedirect();
    $post = Post::where('title', 'Spring Collection Launch')->first();
    expect($post->status)->toBe('scheduled')
        ->and($post->published_at)->toBeNull();

    // Not yet due.
    $this->artisan('posts:publish-scheduled');
    expect($post->fresh()->status)->toBe('scheduled');

    // Due now.
    $post->update(['scheduled_at' => now()->subMinute()]);
    $this->artisan('posts:publish-scheduled');

    expect($post->fresh()->status)->toBe('published')
        ->and($post->fresh()->published_at)->not->toBeNull();
});

it('updates a post and replaces its cover image and tags', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $post = Post::factory()->create(['title' => 'Old Title', 'status' => 'draft']);
    $post->tags()->attach(Tag::create(['name' => 'old-tag', 'slug' => 'old-tag']));

    $this->actingAs($admin)->put("/admin/blog/{$post->id}", [
        'title' => 'New Title',
        'body' => '<p>Updated content.</p>',
        'tags' => 'fresh, updated',
        'status' => 'published',
    ])->assertRedirect();

    $post->refresh();
    expect($post->title)->toBe('New Title')
        ->and($post->status)->toBe('published')
        ->and($post->tags()->pluck('name')->sort()->values()->all())->toBe(['fresh', 'updated']);
});

it('deletes a post', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $post = Post::factory()->create();

    $this->actingAs($admin)->delete("/admin/blog/{$post->id}")->assertRedirect();

    expect(Post::find($post->id))->toBeNull();
});

it('creates and deletes a blog category from the lightweight category panel', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $this->actingAs($admin)->post('/admin/blog-categories', ['name' => 'Skin Care'])
        ->assertRedirect();

    $category = PostCategory::where('name', 'Skin Care')->first();
    expect($category)->not->toBeNull()->and($category->slug)->toBe('skin-care');

    $this->actingAs($admin)->delete("/admin/blog-categories/{$category->id}")->assertRedirect();
    expect(PostCategory::find($category->id))->toBeNull();
});
