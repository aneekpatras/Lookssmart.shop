<?php

use App\Models\CashRegisterShift;
use App\Models\Deal;
use App\Models\DealRedemption;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
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

$openRegister = function (User $user): CashRegisterShift {
    return CashRegisterShift::create([
        'staff_id' => $user->id,
        'opening_float' => 100,
        'status' => 'open',
        'opened_at' => now(),
    ]);
};

$makeService = function (float $price) {
    $category = ServiceCategory::factory()->create();

    return Service::factory()->create([
        'service_category_id' => $category->id,
        'base_price' => $price,
        'is_active' => true,
    ]);
};

it('prices a deal added as a single cart line at its own package price, not the exploded service total', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);

    $serviceA = $makeService(1000);
    $serviceB = $makeService(500);

    $deal = Deal::create([
        'title' => 'Engagement Ready Package', 'slug' => 'engagement-ready', 'type' => 'percent', 'value' => 20,
        'code' => 'ENGAGE20', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'original_price' => 1500, 'deal_price' => 1200,
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['deal_id' => $deal->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 1200]],
    ]);

    $checkout->assertCreated();
    $sale = Sale::findOrFail($checkout->json('sale.id'));

    expect(SaleItem::where('sale_id', $sale->id)->count())->toBe(1);
    $item = SaleItem::where('sale_id', $sale->id)->first();
    expect($item->service_id)->toBeNull()
        ->and($item->deal_id)->toBe($deal->id)
        ->and($item->description)->toBe('Engagement Ready Package')
        ->and((float) $item->unit_price)->toBe(1500.0)
        ->and((float) $item->discount)->toBe(300.0)
        ->and((float) $item->total)->toBe(1200.0)
        ->and((float) $sale->subtotal)->toBe(1500.0)
        ->and((float) $sale->total)->toBe(1200.0);
});

it('prices a deal with no explicit package price via its own percent/fixed formula against the bundle total', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);

    $serviceA = $makeService(200);
    $serviceB = $makeService(300);

    $deal = Deal::create([
        'title' => 'Plain Combo', 'slug' => 'plain-combo', 'type' => 'fixed', 'value' => 50,
        'code' => 'PLAIN50', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['deal_id' => $deal->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 450]],
    ]);

    $checkout->assertCreated();
    $item = SaleItem::where('sale_id', $checkout->json('sale.id'))->first();

    expect((float) $item->unit_price)->toBe(500.0)
        ->and((float) $item->discount)->toBe(50.0)
        ->and((float) $item->total)->toBe(450.0);
});

it('records a real DealRedemption for a deal added as a cart line and enforces its usage_limit', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(1000);

    $deal = Deal::create([
        'title' => 'Limited Package', 'slug' => 'limited-package', 'type' => 'percent', 'value' => 10,
        'code' => 'LIMITED10', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'usage_limit' => 1,
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$service->id]);

    $first = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['deal_id' => $deal->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 900]],
    ]);
    $first->assertCreated();

    expect(DealRedemption::where('deal_id', $deal->id)->count())->toBe(1);

    $second = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['deal_id' => $deal->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 900]],
    ]);

    $second->assertStatus(422);
    expect(DealRedemption::where('deal_id', $deal->id)->count())->toBe(1);
});

it('rejects a cart item with neither service_id nor deal_id, and one with both', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(100);

    $deal = Deal::create([
        'title' => 'Combo', 'slug' => 'combo-both', 'type' => 'percent', 'value' => 10,
        'code' => 'COMBOBOTH', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);

    $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 100]],
    ])->assertStatus(422);

    $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'deal_id' => $deal->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 100]],
    ])->assertStatus(422);
});

it('holds and resumes a deal cart line as a single line, restoring the same deal_id', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(1000);

    $deal = Deal::create([
        'title' => 'Hold Me Package', 'slug' => 'hold-me-package', 'type' => 'percent', 'value' => 10,
        'code' => 'HOLDME10', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$service->id]);

    $hold = $this->actingAs($admin)->postJson('/admin/pos/hold', [
        'items' => [['deal_id' => $deal->id, 'quantity' => 1]],
    ]);
    $hold->assertCreated();
    $saleId = $hold->json('sale.id');

    expect(SaleItem::where('sale_id', $saleId)->first()->deal_id)->toBe($deal->id);

    $this->actingAs($admin)->get("/admin/pos/terminal?resume={$saleId}")
        ->assertInertia(fn ($page) => $page
            ->where('resumeSale.items.0.deal_id', $deal->id)
            ->where('resumeSale.items.0.service_id', null));
});
