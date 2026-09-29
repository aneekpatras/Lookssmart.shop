<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\ServicePriceHistory;
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

it('requires catalog.manage to add a price override', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff');
    $service = Service::factory()->create();

    $response = $this->actingAs($staff)->post('/admin/pricing', [
        'service_id' => $service->id,
        'price_list' => 'weekend',
        'price' => 40,
    ]);

    $response->assertForbidden();
    expect(ServicePrice::where('service_id', $service->id)->exists())->toBeFalse();
});

it('adds a price override and logs it to history with no prior price', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $service = Service::factory()->create();

    $this->actingAs($admin)->post('/admin/pricing', [
        'service_id' => $service->id,
        'price_list' => 'weekend',
        'price' => 45.50,
    ])->assertRedirect();

    $price = ServicePrice::where('service_id', $service->id)->first();
    expect($price)->not->toBeNull()
        ->and($price->price_list)->toBe('weekend');

    $history = ServicePriceHistory::where('service_price_id', $price->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->old_price)->toBeNull()
        ->and((float) $history->new_price)->toBe(45.5)
        ->and($history->changed_by)->toBe($admin->id);
});

it('updates a price override and logs the old and new price', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $service = Service::factory()->create();
    $price = ServicePrice::create([
        'service_id' => $service->id,
        'price_list' => 'weekend',
        'price' => 40,
    ]);

    $this->actingAs($admin)->put("/admin/pricing/{$price->id}", [
        'price_list' => 'weekend',
        'price' => 50,
        'reason' => 'Peak season',
    ])->assertRedirect();

    expect($price->fresh()->price)->toBe('50.00');

    $history = ServicePriceHistory::where('service_price_id', $price->id)->latest('id')->first();
    expect((float) $history->old_price)->toBe(40.0)
        ->and((float) $history->new_price)->toBe(50.0)
        ->and($history->reason)->toBe('Peak season');
});

it('deletes a price override', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $service = Service::factory()->create();
    $price = ServicePrice::create(['service_id' => $service->id, 'price_list' => 'weekend', 'price' => 40]);

    $this->actingAs($admin)->delete("/admin/pricing/{$price->id}")->assertRedirect();

    expect(ServicePrice::find($price->id))->toBeNull();
});

it('bulk-adjusts every service base price in a category and logs each change', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    $serviceA = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 100]);
    $serviceB = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 50]);
    $otherCategory = ServiceCategory::factory()->create();
    $unaffected = Service::factory()->create(['service_category_id' => $otherCategory->id, 'base_price' => 200]);

    $this->actingAs($admin)->post('/admin/pricing/bulk-adjust', [
        'service_category_id' => $category->id,
        'percent' => 10,
    ])->assertRedirect();

    expect((float) $serviceA->fresh()->base_price)->toBe(110.0)
        ->and((float) $serviceB->fresh()->base_price)->toBe(55.0)
        ->and((float) $unaffected->fresh()->base_price)->toBe(200.0);

    expect(ServicePriceHistory::where('service_id', $serviceA->id)->count())->toBe(1)
        ->and(ServicePriceHistory::where('service_id', $serviceB->id)->count())->toBe(1)
        ->and(ServicePriceHistory::where('service_id', $unaffected->id)->count())->toBe(0);

    $historyA = ServicePriceHistory::where('service_id', $serviceA->id)->first();
    expect((float) $historyA->old_price)->toBe(100.0)
        ->and((float) $historyA->new_price)->toBe(110.0)
        ->and($historyA->price_list)->toBe('base');
});

it('rejects a bulk adjustment from a user without catalog.manage', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff');
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 100]);

    $response = $this->actingAs($staff)->post('/admin/pricing/bulk-adjust', [
        'service_category_id' => $category->id,
        'percent' => 10,
    ]);

    $response->assertForbidden();
    expect((float) $service->fresh()->base_price)->toBe(100.0);
});
