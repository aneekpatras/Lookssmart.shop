<?php

use App\Models\CashRegisterShift;
use App\Models\Deal;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
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

it('surfaces active deals with their bundled services for the POS terminal', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);

    $serviceA = $makeService(1000);
    $serviceB = $makeService(500);

    $deal = Deal::create([
        'title' => 'Bridal Combo', 'slug' => 'bridal-combo', 'type' => 'percent', 'value' => 20,
        'code' => 'BRIDAL20', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'original_price' => 1500, 'deal_price' => 1200,
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    // An expired deal must never show up as an addable option.
    Deal::create([
        'title' => 'Expired Deal', 'slug' => 'expired-deal', 'type' => 'percent', 'value' => 10,
        'code' => 'EXPIRED', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);

    $response = $this->actingAs($receptionist)->getJson('/admin/pos/search/deals?q=Bridal');

    $response->assertOk();
    $deals = $response->json('deals');

    expect($deals)->toHaveCount(1)
        ->and($deals[0]['title'])->toBe('Bridal Combo')
        ->and($deals[0]['code'])->toBe('BRIDAL20')
        ->and($deals[0]['services'])->toHaveCount(2);
});

it('still supports manually adding a deal\'s services plus its own coupon code, a separate path from the single-line addDeal() flow', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);

    $serviceA = $makeService(1000);
    $serviceB = $makeService(500);

    $deal = Deal::create([
        'title' => 'Bridal Combo', 'slug' => 'bridal-combo-2', 'type' => 'percent', 'value' => 20,
        'code' => 'BRIDAL20B', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);
    $deal->services()->sync([$serviceA->id, $serviceB->id]);

    // As of Phase 12 sub-step 8, Terminal.tsx's addDeal() sends a single deal_id line instead (see
    // PosDealCartLineTest.php) — but the coupon-code path itself is untouched and still real
    // (e.g. a cashier manually adding the same services and typing the deal's code by hand), so it
    // stays covered here rather than being deleted along with the button that used to drive it.
    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [
            ['service_id' => $serviceA->id, 'quantity' => 1],
            ['service_id' => $serviceB->id, 'quantity' => 1],
        ],
        'coupon_code' => 'BRIDAL20B',
        'payments' => [['method' => 'cash', 'amount' => 1200]],
    ]);

    $checkout->assertCreated();
    $sale = Sale::findOrFail($checkout->json('sale.id'));

    expect((float) $sale->subtotal)->toBe(1500.0)
        ->and((float) $sale->discount)->toBe(300.0) // 20% of 1500
        ->and((float) $sale->total)->toBe(1200.0);
});
