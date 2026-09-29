<?php

use App\Models\Deal;
use App\Models\Service;
use App\Models\ServiceCategory;
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

it('requires catalog.manage to create a deal', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff');

    $response = $this->actingAs($staff)->post('/admin/deals', [
        'title' => 'Summer Sale',
        'type' => 'percent',
        'value' => 20,
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
    ]);

    $response->assertForbidden();
    expect(Deal::where('title', 'Summer Sale')->exists())->toBeFalse();
});

it('creates a deal with an auto-derived slug, uppercased code, and service+category links', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $service = Service::factory()->create();
    $category = ServiceCategory::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Summer Sale',
        'type' => 'percent',
        'value' => 20,
        'code' => 'summer20',
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
        'service_ids' => [$service->id],
        'category_ids' => [$category->id],
    ]);

    $response->assertRedirect(route('admin.deals'));
    $deal = Deal::where('title', 'Summer Sale')->first();
    expect($deal)->not->toBeNull()
        ->and($deal->slug)->toBe('summer-sale')
        ->and($deal->code)->toBe('SUMMER20')
        ->and($deal->services()->pluck('services.id')->all())->toBe([$service->id])
        ->and($deal->categories()->pluck('service_categories.id')->all())->toBe([$category->id]);
});

it('enforces a unique coupon code case-insensitively', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Deal::factory()->create(['code' => 'SAVE10']);

    $response = $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Duplicate Code Deal',
        'type' => 'fixed',
        'value' => 5,
        'code' => 'save10',
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
    ]);

    $response->assertSessionHasErrors('code');
});

it('rejects an end date before the start date', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $response = $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Bad Window',
        'type' => 'fixed',
        'value' => 5,
        'starts_at' => now(),
        'ends_at' => now()->subDay(),
    ]);

    $response->assertSessionHasErrors('ends_at');
});

it('updates a deal, replacing its service/category links via sync', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $deal = Deal::factory()->create();
    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();
    $deal->services()->sync([$serviceA->id]);

    $this->actingAs($admin)->put("/admin/deals/{$deal->id}", [
        'title' => $deal->title,
        'type' => $deal->type,
        'value' => $deal->value,
        'starts_at' => $deal->starts_at,
        'ends_at' => $deal->ends_at,
        'service_ids' => [$serviceB->id],
    ])->assertRedirect(route('admin.deals'));

    expect($deal->services()->pluck('services.id')->all())->toBe([$serviceB->id]);
});

it('deletes a deal', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $deal = Deal::factory()->create();

    $this->actingAs($admin)->delete("/admin/deals/{$deal->id}")->assertRedirect();

    expect(Deal::find($deal->id))->toBeNull();
});

it('saves a pasted stock_image_url when no file is uploaded', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $this->actingAs($admin)->post('/admin/deals', [
        'title' => 'Summer Sale',
        'type' => 'percent',
        'value' => 20,
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
        'stock_image_url' => 'https://images.example.com/summer.jpg',
    ])->assertRedirect(route('admin.deals'));

    $deal = Deal::where('title', 'Summer Sale')->firstOrFail();
    expect($deal->stock_image_url)->toBe('https://images.example.com/summer.jpg')
        ->and($deal->image_path)->toBeNull();
});

/**
 * This is also the regression test for the PUT-with-multipart-body bug: Inertia's `useForm().put()`
 * with `forceFormData: true` sends a real HTTP PUT, which PHP never parses a multipart body for —
 * DealForm.tsx now spoofs PUT via a POST + `_method` field instead (see DealForm.tsx), which is
 * exactly what this test drives.
 */
it('lets an uploaded file take precedence over stock_image_url on update, deleting the old local file', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $deal = Deal::factory()->create(['image_path' => null, 'stock_image_url' => 'https://images.example.com/old.jpg']);

    $this->actingAs($admin)->post("/admin/deals/{$deal->id}", [
        '_method' => 'put',
        'title' => $deal->title,
        'type' => $deal->type,
        'value' => $deal->value,
        'starts_at' => $deal->starts_at,
        'ends_at' => $deal->ends_at,
        'image' => UploadedFile::fake()->image('deal.jpg'),
    ])->assertRedirect(route('admin.deals'));

    $deal->refresh();
    expect($deal->stock_image_url)->toBeNull()
        ->and($deal->image_path)->not->toBeNull();
});
