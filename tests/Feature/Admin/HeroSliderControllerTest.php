<?php

use App\Models\Slide;
use App\Models\Slider;
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

it('requires cms.manage to create a slide', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no cms.manage
    $image = UploadedFile::fake()->image('hero.jpg', 1600, 900);

    $response = $this->actingAs($staff)->post('/admin/slider', [
        'heading' => 'Spring Glow',
        'image' => $image,
    ]);

    $response->assertForbidden();
    expect(Slide::count())->toBe(0);
});

it('lets an admin create a slide with a real uploaded image on the homepage slider', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $image = UploadedFile::fake()->image('hero.jpg', 1600, 900);

    $response = $this->actingAs($admin)->post('/admin/slider', [
        'heading' => 'Spring Glow',
        'subheading' => 'Book your seasonal refresh',
        'cta_text' => 'Book now',
        'cta_url' => '/book',
        'image' => $image,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $slide = Slide::where('heading', 'Spring Glow')->first();
    expect($slide)->not->toBeNull()
        ->and($slide->image_path)->not->toBeNull()
        ->and($slide->slider->slug)->toBe('homepage-hero');

    Storage::disk('public')->assertExists($slide->image_path);
});

it('rejects a php file renamed to .jpg for a slide image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $fakePhp = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>');

    $response = $this->actingAs($admin)->post('/admin/slider', [
        'heading' => 'Spring Glow',
        'image' => $fakePhp,
    ]);

    $response->assertSessionHasErrors('file');
    expect(Slide::count())->toBe(0);
});

it('toggles a slide active status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $slider = Slider::factory()->create();
    $slide = Slide::factory()->create(['slider_id' => $slider->id, 'is_active' => true]);

    $this->actingAs($admin)->put("/admin/slider/{$slide->id}", [
        'is_active' => false,
    ])->assertRedirect();

    expect($slide->fresh()->is_active)->toBeFalse();
});

it('reorders slides by sort', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $slider = Slider::factory()->create();
    $first = Slide::factory()->create(['slider_id' => $slider->id, 'sort' => 0]);
    $second = Slide::factory()->create(['slider_id' => $slider->id, 'sort' => 1]);

    $this->actingAs($admin)->post('/admin/slider/reorder', [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->sort)->toBe(0)
        ->and($first->fresh()->sort)->toBe(1);
});

it('deletes a slide and its stored image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $slider = Slider::factory()->create();
    Storage::disk('public')->put('sliders/existing.jpg', 'fake-image-bytes');
    $slide = Slide::factory()->create(['slider_id' => $slider->id, 'image_path' => 'sliders/existing.jpg']);

    $this->actingAs($admin)->delete("/admin/slider/{$slide->id}")->assertRedirect();

    expect(Slide::find($slide->id))->toBeNull();
    Storage::disk('public')->assertMissing('sliders/existing.jpg');
});
