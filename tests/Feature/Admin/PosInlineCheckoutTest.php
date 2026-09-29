<?php

use App\Models\CashRegisterShift;
use App\Models\Sale;
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

it('quick-creates a walk-in customer with a real customer profile and role', function () use ($makeConfirmedUser, $openRegister) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'name' => 'Ayesha Khan',
        'email' => 'ayesha@example.test',
        'phone' => '03001234567',
    ]);

    $response->assertCreated();
    $customerId = $response->json('customer.id');

    $user = User::findOrFail($customerId);
    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->customerProfile)->not->toBeNull()
        ->and($response->json('customer.loyalty_points'))->toBe(0);
});

it('rejects a quick-create customer with a duplicate email', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $receptionist = $makeConfirmedUser('receptionist');
    User::factory()->create(['email' => 'dup@example.test']);

    $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'name' => 'Someone',
        'email' => 'dup@example.test',
    ])->assertStatus(422);
});

it('quick-creates a walk-in customer with no email at all, using a real unique placeholder', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $receptionist = $makeConfirmedUser('receptionist');

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'name' => 'Walk-in Person',
    ]);

    $response->assertCreated();
    $user = User::findOrFail($response->json('customer.id'));

    expect($user->email)->not->toBeEmpty()
        ->and($user->hasRole('customer'))->toBeTrue()
        ->and(User::where('email', $user->email)->count())->toBe(1);
});

it('still requires a name to quick-create a customer', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $receptionist = $makeConfirmedUser('receptionist');

    $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'email' => 'someone@example.test',
    ])->assertStatus(422);
});

it('rejects an invalid email format when one is actually provided, but allows blank', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $receptionist = $makeConfirmedUser('receptionist');

    $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'name' => 'Bad Email Person',
        'email' => 'not-an-email',
    ])->assertStatus(422);

    $this->actingAs($receptionist)->postJson('/admin/pos/customers', [
        'name' => 'Blank Email Person',
        'email' => '',
    ])->assertCreated();
});

it('applies a manual cart-level discount percent additively with a per-line discount', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(1000); // qty 1, line discount 100 -> after item discount 900; 10% manual -> 90

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1, 'discount' => 100]],
        'discount_percent' => 10,
        'payments' => [['method' => 'cash', 'amount' => 810]],
    ]);

    $checkout->assertCreated();
    $sale = Sale::findOrFail($checkout->json('sale.id'));

    expect((float) $sale->subtotal)->toBe(1000.0)
        ->and((float) $sale->discount)->toBe(190.0) // 100 item + 90 manual
        ->and((float) $sale->discount_percent)->toBe(10.0)
        ->and((float) $sale->total)->toBe(810.0);
});

it('overrides the global tax rate with a manual cart-level tax percent', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 17, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(100);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'tax_rate_percent' => 5,
        'payments' => [['method' => 'cash', 'amount' => 105]],
    ]);

    $checkout->assertCreated();
    $sale = Sale::findOrFail($checkout->json('sale.id'));

    expect((float) $sale->tax)->toBe(5.0)
        ->and((float) $sale->tax_rate_percent)->toBe(5.0)
        ->and((float) $sale->total)->toBe(105.0);
});

it('never lets the manual discount push the total discount past the subtotal', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(50);

    $response = $this->actingAs($admin)->postJson('/admin/pos/apply-coupon', [
        'items' => [['service_id' => $service->id, 'quantity' => 1, 'discount' => 50]],
        'discount_percent' => 100,
    ]);

    $response->assertOk();
    expect((float) $response->json('discount'))->toBe(50.0)
        ->and((float) $response->json('total'))->toBe(0.0);
});

it('persists the manual discount/tax percent on a held sale and restores them on resume', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(200);

    $hold = $this->actingAs($receptionist)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'discount_percent' => 15,
        'tax_rate_percent' => 8,
    ]);
    $saleId = $hold->json('sale.id');

    expect((float) Sale::findOrFail($saleId)->discount_percent)->toBe(15.0)
        ->and((float) Sale::findOrFail($saleId)->tax_rate_percent)->toBe(8.0);

    $this->actingAs($receptionist)->get("/admin/pos/terminal?resume={$saleId}")
        ->assertInertia(fn ($page) => $page
            ->where('resumeSale.discount_percent', '15.00')
            ->where('resumeSale.tax_rate_percent', '8.00'));
});
