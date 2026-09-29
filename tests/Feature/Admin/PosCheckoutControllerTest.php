<?php

use App\Models\Booking;
use App\Models\CashRegisterShift;
use App\Models\CustomerProfile;
use App\Models\Deal;
use App\Models\DealRedemption;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
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

$makeService = function (float $price): Service {
    $category = ServiceCategory::factory()->create();

    return Service::factory()->create([
        'service_category_id' => $category->id,
        'base_price' => $price,
        'is_active' => true,
    ]);
};

it('requires pos.sell to reach the terminal', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no pos.sell

    $this->actingAs($staff)->get('/admin/pos/terminal')->assertForbidden();
});

it('redirects the terminal to the register page when no register is open', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');

    $this->actingAs($receptionist)->get('/admin/pos/terminal')->assertRedirect('/admin/pos');
});

it('refuses to checkout when no register is open', function () use ($makeConfirmedUser, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $service = $makeService(50);

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50]],
    ])->assertStatus(409);

    expect(Sale::count())->toBe(0);
});

it('rings a cash sale and persists the sale, sale items, and payment rows', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(40);

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 2]],
        'payments' => [['method' => 'cash', 'amount' => 80]],
    ]);

    $response->assertCreated();
    $saleId = $response->json('sale.id');

    $sale = Sale::findOrFail($saleId);
    expect($sale->status)->toBe('completed')
        ->and((float) $sale->subtotal)->toBe(80.0)
        ->and((float) $sale->total)->toBe(80.0)
        ->and($sale->created_by)->toBe($receptionist->id);

    expect(SaleItem::where('sale_id', $sale->id)->count())->toBe(1);
    $item = SaleItem::where('sale_id', $sale->id)->first();
    expect($item->quantity)->toBe(2)
        ->and((float) $item->unit_price)->toBe(40.0)
        ->and((float) $item->total)->toBe(80.0);

    $payment = Payment::where('sale_id', $sale->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('succeeded')
        ->and((float) $payment->amount)->toBe(80.0)
        ->and($payment->booking_id)->toBeNull();
});

it('calculates exact subtotal, item discount, coupon discount, and tax', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 10, 'group' => 'booking', 'is_encrypted' => false]);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);

    $serviceA = $makeService(100); // qty 1, line discount 10 -> line total 90
    $serviceB = $makeService(50); // qty 2, no discount -> line total 100

    Deal::create([
        'title' => '10% Off', 'slug' => '10-percent-off', 'type' => 'percent', 'value' => 10,
        'code' => 'SAVE10', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);

    // subtotal = 100 + 100 = 200; item_discount = 10; after item discount = 190;
    // coupon_discount = 10% of 190 = 19; total_discount = 29; taxable = 171; tax = 17.10; total = 188.10
    $response = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [
            ['service_id' => $serviceA->id, 'quantity' => 1, 'discount' => 10],
            ['service_id' => $serviceB->id, 'quantity' => 2],
        ],
        'coupon_code' => 'save10',
        'payments' => [['method' => 'cash', 'amount' => 188.10]],
    ]);

    $response->assertCreated();
    $sale = Sale::findOrFail($response->json('sale.id'));

    expect((float) $sale->subtotal)->toBe(200.0)
        ->and((float) $sale->discount)->toBe(29.0)
        ->and((float) $sale->tax)->toBe(17.1)
        ->and((float) $sale->total)->toBe(188.1);

    $redemption = DealRedemption::where('sale_id', $sale->id)->first();
    expect($redemption)->not->toBeNull()
        ->and($redemption->code_used)->toBe('SAVE10')
        ->and((float) $redemption->discount_amount)->toBe(19.0);
});

it('rejects checkout when payment amounts do not add up to the total', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(50);

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 40]],
    ])->assertStatus(422)->assertJsonValidationErrors('payments');

    expect(Sale::count())->toBe(0);
});

it('supports a split payment across cash and card', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(100);

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [
            ['method' => 'cash', 'amount' => 60],
            ['method' => 'card', 'amount' => 40, 'reference' => 'VISA 1234'],
        ],
    ]);

    $response->assertCreated();
    $sale = Sale::findOrFail($response->json('sale.id'));
    expect(Payment::where('sale_id', $sale->id)->count())->toBe(2)
        ->and(Payment::where('sale_id', $sale->id)->where('method', 'card')->value('gateway_ref'))->toBe('VISA 1234');
});

it('redeems loyalty points as a payment method and decrements the customer balance', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(20);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    CustomerProfile::factory()->create(['user_id' => $customer->id, 'loyalty_points' => 5000]);

    // 100 points = $1, so $20 costs 2000 points, leaving 3000.
    $response = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'payments' => [['method' => 'loyalty_points', 'amount' => 20]],
    ]);

    $response->assertCreated();
    expect(CustomerProfile::where('user_id', $customer->id)->value('loyalty_points'))->toBe(3000);
});

it('rejects a loyalty points payment without a customer selected', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(20);

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'loyalty_points', 'amount' => 20]],
    ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
});

it('rejects a loyalty points payment that exceeds the customer balance', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(20);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    CustomerProfile::factory()->create(['user_id' => $customer->id, 'loyalty_points' => 100]); // only $1 worth

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'payments' => [['method' => 'loyalty_points', 'amount' => 20]],
    ])->assertStatus(422)->assertJsonValidationErrors('payments');

    expect(CustomerProfile::where('user_id', $customer->id)->value('loyalty_points'))->toBe(100);
});

it('links a completed sale to a checked-in booking and transitions it to completed', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(60);
    $staffMember = Staff::factory()->create();

    $booking = Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'checked_in',
        'starts_at' => now(),
        'ends_at' => now()->addMinutes(45),
    ]);

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'booking_id' => $booking->id,
        'payments' => [['method' => 'cash', 'amount' => 60]],
    ]);

    $response->assertCreated();
    $sale = Sale::findOrFail($response->json('sale.id'));
    expect($sale->booking_id)->toBe($booking->id);
    expect($booking->fresh()->status)->toBe('completed');
});

it('refuses to link a sale to a booking that is not checked in', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(60);
    $staffMember = Staff::factory()->create();

    $booking = Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'confirmed',
        'starts_at' => now(),
        'ends_at' => now()->addMinutes(45),
    ]);

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'booking_id' => $booking->id,
        'payments' => [['method' => 'cash', 'amount' => 60]],
    ])->assertStatus(422)->assertJsonValidationErrors('booking_id');

    expect($booking->fresh()->status)->toBe('confirmed');
    expect(Sale::count())->toBe(0);
});

it('enforces a coupon\'s per-user redemption limit across POS sales', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(50);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    CustomerProfile::factory()->create(['user_id' => $customer->id]);

    $deal = Deal::create([
        'title' => 'One Time $5 Off', 'slug' => 'one-time-5-off', 'type' => 'fixed', 'value' => 5,
        'code' => 'ONCE5', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'per_user_limit' => 1, 'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);

    $first = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'coupon_code' => 'ONCE5',
        'payments' => [['method' => 'cash', 'amount' => 45]],
    ]);
    $first->assertCreated();

    expect(DealRedemption::where('deal_id', $deal->id)->where('customer_id', $customer->id)->count())->toBe(1);

    // Same customer, same coupon, a second time — must now be rejected since the first POS
    // redemption is counted (proving deal_redemptions.sale_id actually feeds PriceQuoteService's
    // per_user_limit check, not just booking-channel redemptions).
    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'coupon_code' => 'ONCE5',
        'payments' => [['method' => 'cash', 'amount' => 50]],
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('renders a printable receipt for a completed sale', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(30);

    $checkout = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 30]],
    ]);
    $saleId = $checkout->json('sale.id');

    $response = $this->actingAs($receptionist)->getJson("/admin/pos/sales/{$saleId}/receipt");

    $response->assertOk()
        ->assertJsonPath('sale.total', '30.00')
        ->assertJsonCount(1, 'sale.items')
        ->assertJsonCount(1, 'sale.payments');
});
