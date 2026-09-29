<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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

it('requires catalog.manage to create a service', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staffUser = $makeConfirmedUser('staff');
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($staffUser)->post('/admin/services', [
        'service_category_id' => $category->id,
        'name' => 'Manicure',
        'duration_min' => 30,
        'base_price' => 25,
    ]);

    $response->assertForbidden();
    expect(Service::where('name', 'Manicure')->exists())->toBeFalse();
});

it('creates a service with sanitized description, slug, and staff assignment', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    $staffMember = Staff::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/services', [
        'service_category_id' => $category->id,
        'name' => 'Manicure',
        'description' => '<script>alert(1)</script><p>Relaxing manicure</p>',
        'duration_min' => 30,
        'base_price' => 25,
        'staff_ids' => [$staffMember->id],
    ]);

    $response->assertRedirect(route('admin.services'));
    $service = Service::where('name', 'Manicure')->first();
    expect($service)->not->toBeNull()
        ->and($service->slug)->toBe('manicure')
        ->and($service->description)->not->toContain('<script>')
        ->and($service->staff()->pluck('staff.id')->all())->toBe([$staffMember->id]);
});

it('enforces a unique sku on update', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    Service::factory()->create(['service_category_id' => $category->id, 'sku' => 'MANI-01']);
    $target = Service::factory()->create(['service_category_id' => $category->id, 'sku' => 'PEDI-01']);

    $response = $this->actingAs($admin)->put("/admin/services/{$target->id}", [
        'service_category_id' => $category->id,
        'sku' => 'MANI-01',
        'name' => $target->name,
        'duration_min' => $target->duration_min,
        'base_price' => $target->base_price,
    ]);

    $response->assertSessionHasErrors('sku');
});

it('deletes a service', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $service = Service::factory()->create();

    $this->actingAs($admin)->delete("/admin/services/{$service->id}")->assertRedirect();

    expect(Service::find($service->id))->toBeNull();
});

it('saves a pasted stock_image_url when no file is uploaded', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();

    $this->actingAs($admin)->post('/admin/services', [
        'service_category_id' => $category->id,
        'name' => 'Manicure',
        'duration_min' => 30,
        'base_price' => 25,
        'stock_image_url' => 'https://images.example.com/manicure.jpg',
    ])->assertRedirect(route('admin.services'));

    $service = Service::where('name', 'Manicure')->firstOrFail();
    expect($service->stock_image_url)->toBe('https://images.example.com/manicure.jpg')
        ->and($service->getFirstMediaUrl('image'))->toBe('');
});

it('lets an uploaded file take precedence over stock_image_url, and switching back to a url clears the upload', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'stock_image_url' => 'https://images.example.com/old.jpg',
    ]);

    $this->actingAs($admin)->put("/admin/services/{$service->id}", [
        'service_category_id' => $service->service_category_id,
        'name' => $service->name,
        'duration_min' => $service->duration_min,
        'base_price' => $service->base_price,
        'image' => UploadedFile::fake()->image('service.jpg'),
    ])->assertRedirect(route('admin.services'));

    $service->refresh();
    expect($service->stock_image_url)->toBeNull()
        ->and($service->getFirstMediaUrl('image'))->not->toBe('');

    // Now switch to a URL — the uploaded media must actually be dropped, or the new URL would
    // silently never be used (media always wins over stock_image_url in the display precedence).
    $this->actingAs($admin)->put("/admin/services/{$service->id}", [
        'service_category_id' => $service->service_category_id,
        'name' => $service->name,
        'duration_min' => $service->duration_min,
        'base_price' => $service->base_price,
        'stock_image_url' => 'https://images.example.com/new.jpg',
    ])->assertRedirect(route('admin.services'));

    $service->refresh();
    expect($service->stock_image_url)->toBe('https://images.example.com/new.jpg')
        ->and($service->getFirstMediaUrl('image'))->toBe('');
});

it('exports the full service catalog as a downloadable spreadsheet', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Service::factory()->create(['stock_image_url' => 'https://images.example.com/x.jpg']);

    $response = $this->actingAs($admin)->get('/admin/services/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('spreadsheet');
});
