<?php

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('requires cms.manage to create a gallery category', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no cms.manage

    $response = $this->actingAs($staff)->post('/admin/gallery-categories', ['name' => 'Bridal']);

    $response->assertForbidden();
    expect(GalleryCategory::where('name', 'Bridal')->exists())->toBeFalse();
});

it('lets an admin create a gallery category', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $response = $this->actingAs($admin)->post('/admin/gallery-categories', [
        'name' => 'Looks Smart Brides',
        'subtitle' => 'Real bridal transformations from our studio.',
    ]);

    $response->assertRedirect();
    $category = GalleryCategory::where('name', 'Looks Smart Brides')->first();
    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('looks-smart-brides')
        ->and($category->subtitle)->toBe('Real bridal transformations from our studio.');
});

it('updates and deletes a gallery category', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = GalleryCategory::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)->put("/admin/gallery-categories/{$category->id}", [
        'name' => 'New Name',
        'subtitle' => 'Updated subtitle',
    ])->assertRedirect();

    expect($category->fresh()->name)->toBe('New Name')
        ->and($category->fresh()->subtitle)->toBe('Updated subtitle');

    $this->actingAs($admin)->delete("/admin/gallery-categories/{$category->id}")->assertRedirect();
    expect(GalleryCategory::find($category->id))->toBeNull();
});

it('reorders gallery categories by sort', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $first = GalleryCategory::factory()->create(['sort' => 0]);
    $second = GalleryCategory::factory()->create(['sort' => 1]);

    $this->actingAs($admin)->post('/admin/gallery-categories/reorder', [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->sort)->toBe(0)
        ->and($first->fresh()->sort)->toBe(1);
});

it('deleting a category leaves its albums intact but uncategorized', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = GalleryCategory::factory()->create();
    $gallery = Gallery::factory()->create(['gallery_category_id' => $category->id]);

    $this->actingAs($admin)->delete("/admin/gallery-categories/{$category->id}")->assertRedirect();

    expect(Gallery::find($gallery->id))->not->toBeNull()
        ->and($gallery->fresh()->gallery_category_id)->toBeNull();
});
