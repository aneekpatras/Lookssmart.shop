<?php

use App\Models\ServiceCategory;
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

it('requires catalog.manage to create a category', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no catalog.manage

    $response = $this->actingAs($staff)->post('/admin/service-categories', ['name' => 'Nails']);

    $response->assertForbidden();
    expect(ServiceCategory::where('name', 'Nails')->exists())->toBeFalse();
});

it('lets an admin create a category with a real uploaded image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $image = UploadedFile::fake()->image('nails.jpg', 400, 400);

    $response = $this->actingAs($admin)->post('/admin/service-categories', [
        'name' => 'Nails',
        'is_active' => true,
        'image' => $image,
    ]);

    $response->assertRedirect();
    $category = ServiceCategory::where('name', 'Nails')->first();
    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('nails')
        ->and($category->getFirstMedia('image'))->not->toBeNull();
});

it('rejects a php file renamed to .jpg for a category image', function () use ($makeConfirmedUser) {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $fakePhp = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>');

    $response = $this->actingAs($admin)->post('/admin/service-categories', [
        'name' => 'Nails',
        'image' => $fakePhp,
    ]);

    $response->assertSessionHasErrors('image');
});

it('updates and deletes a category', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)->put("/admin/service-categories/{$category->id}", [
        'name' => 'New Name',
        'is_active' => true,
    ])->assertRedirect();

    expect($category->fresh()->name)->toBe('New Name');

    $this->actingAs($admin)->delete("/admin/service-categories/{$category->id}")->assertRedirect();
    expect(ServiceCategory::find($category->id))->toBeNull();
});

it('reorders categories by sort', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $first = ServiceCategory::factory()->create(['sort' => 0]);
    $second = ServiceCategory::factory()->create(['sort' => 1]);

    $this->actingAs($admin)->post('/admin/service-categories/reorder', [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->sort)->toBe(0)
        ->and($first->fresh()->sort)->toBe(1);
});
