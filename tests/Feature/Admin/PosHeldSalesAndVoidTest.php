<?php

use App\Models\CashRegisterShift;
use App\Models\CustomerProfile;
use App\Models\Deal;
use App\Models\DealRedemption;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
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

$makeService = function (float $price): Service {
    $category = ServiceCategory::factory()->create();

    return Service::factory()->create([
        'service_category_id' => $category->id,
        'base_price' => $price,
        'is_active' => true,
    ]);
};

it('holds a sale as status open with no payment rows', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(60);

    $response = $this->actingAs($receptionist)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
    ]);

    $response->assertCreated();
    $sale = Sale::findOrFail($response->json('sale.id'));

    expect($sale->status)->toBe('open')
        ->and(SaleItem::where('sale_id', $sale->id)->count())->toBe(1)
        ->and(Payment::where('sale_id', $sale->id)->count())->toBe(0);
});

it('lists only open sales on the held sales page', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(30);

    $this->actingAs($receptionist)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
    ])->assertCreated();

    $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 30]],
    ])->assertCreated();

    $this->actingAs($receptionist)
        ->get('/admin/pos/held-sales')
        ->assertInertia(fn ($page) => $page->component('Admin/POS/HeldSales')->has('sales', 1));
});

it('resumes a held sale and finalizes the same row without creating a duplicate', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(70);

    $hold = $this->actingAs($receptionist)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
    ]);
    $heldSaleId = $hold->json('sale.id');
    $originalSaleNumber = Sale::findOrFail($heldSaleId)->sale_number;

    $this->actingAs($receptionist)->get("/admin/pos/terminal?resume={$heldSaleId}")
        ->assertInertia(fn ($page) => $page->where('resumeSale.id', $heldSaleId));

    $checkout = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'resume_sale_id' => $heldSaleId,
        'payments' => [['method' => 'cash', 'amount' => 70]],
    ]);

    $checkout->assertCreated();
    expect($checkout->json('sale.id'))->toBe($heldSaleId)
        ->and(Sale::count())->toBe(1);

    $sale = Sale::findOrFail($heldSaleId);
    expect($sale->status)->toBe('completed')
        ->and($sale->sale_number)->toBe($originalSaleNumber)
        ->and(Payment::where('sale_id', $sale->id)->count())->toBe(1);
});

it('discards a held sale with a hard delete', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(20);

    $hold = $this->actingAs($receptionist)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
    ]);
    $saleId = $hold->json('sale.id');

    $this->actingAs($receptionist)->delete("/admin/pos/held-sales/{$saleId}")->assertRedirect();

    expect(Sale::withTrashed()->find($saleId))->toBeNull()
        ->and(SaleItem::where('sale_id', $saleId)->count())->toBe(0);
});

it('refuses to discard a completed sale', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $openRegister($receptionist);
    $service = $makeService(20);

    $sale = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 20]],
    ])->json('sale.id');

    $this->actingAs($receptionist)->delete("/admin/pos/held-sales/{$sale}")->assertStatus(409);
});

it('voids a completed sale, refunds its payments, restores loyalty points, and frees the coupon redemption', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(100);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    CustomerProfile::create(['user_id' => $customer->id, 'loyalty_points' => 5000]);

    $deal = Deal::create([
        'title' => '10 off', 'slug' => 'void-test-deal', 'type' => 'fixed', 'value' => 5,
        'code' => 'VOIDTEST', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        'is_stackable' => false, 'is_auto_apply' => false, 'is_active' => true,
    ]);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'coupon_code' => 'VOIDTEST',
        'payments' => [
            ['method' => 'loyalty_points', 'amount' => 20],
            ['method' => 'cash', 'amount' => 75],
        ],
    ]);
    $checkout->assertCreated();
    $saleId = $checkout->json('sale.id');

    // 100 subtotal - 5 fixed coupon discount = 95 total; 20 of it paid via loyalty points
    // (2000 points at 100 pts/$1), leaving 5000 - 2000 = 3000.
    expect(CustomerProfile::where('user_id', $customer->id)->value('loyalty_points'))->toBe(3000)
        ->and(DealRedemption::where('sale_id', $saleId)->count())->toBe(1);

    $this->actingAs($admin)->post("/admin/pos/sales/{$saleId}/void")->assertRedirect();

    $sale = Sale::withTrashed()->findOrFail($saleId);
    expect($sale->status)->toBe('voided')
        ->and($sale->trashed())->toBeTrue()
        ->and(Payment::where('sale_id', $saleId)->where('status', 'succeeded')->count())->toBe(0)
        ->and(Payment::where('sale_id', $saleId)->where('status', 'refunded')->count())->toBe(2)
        ->and(DealRedemption::where('sale_id', $saleId)->count())->toBe(0)
        ->and(CustomerProfile::where('user_id', $customer->id)->value('loyalty_points'))->toBe(5000);
});

it('excludes a voided sale\'s cash from the shift\'s expected cash total', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $shift = $openRegister($admin);
    $service = $makeService(50);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 50]],
    ]);
    $saleId = $checkout->json('sale.id');

    $this->actingAs($admin)->get('/admin/pos')
        ->assertInertia(fn ($page) => $page->where('shift.current_expected_cash', '150.00'));

    $this->actingAs($admin)->post("/admin/pos/sales/{$saleId}/void")->assertRedirect();

    $this->actingAs($admin)->get('/admin/pos')
        ->assertInertia(fn ($page) => $page->where('shift.current_expected_cash', '100.00'));
});

it('refuses to void an already-voided sale', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(20);

    $saleId = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 20]],
    ])->json('sale.id');

    $this->actingAs($admin)->post("/admin/pos/sales/{$saleId}/void")->assertRedirect();
    $this->actingAs($admin)->post("/admin/pos/sales/{$saleId}/void")->assertStatus(409);
});

it('requires pos.refund to void a sale', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist'); // pos.sell only, no pos.refund
    $openRegister($receptionist);
    $service = $makeService(20);

    $saleId = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 20]],
    ])->json('sale.id');

    $this->actingAs($receptionist)->post("/admin/pos/sales/{$saleId}/void")->assertForbidden();
});

it('persists cash tendered and change on the sale receipt', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(80);

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 80, 'tendered_amount' => 100]],
    ]);
    $saleId = $checkout->json('sale.id');

    $this->actingAs($admin)->get("/admin/pos/sales/{$saleId}")
        ->assertInertia(fn ($page) => $page
            ->component('Admin/POS/Invoice')
            ->where('sale.payments.0.tendered_amount', '100.00')
            ->where('sale.payments.0.change', '20.00'));
});

it('filters and paginates sales history and excludes held sales', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(40);

    $this->actingAs($admin)->postJson('/admin/pos/hold', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
    ])->assertCreated();

    $checkout = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 40]],
    ]);
    $saleNumber = Sale::findOrFail($checkout->json('sale.id'))->sale_number;

    $this->actingAs($admin)
        ->get('/admin/pos/sales-history')
        ->assertInertia(fn ($page) => $page->has('sales.data', 1));

    $this->actingAs($admin)
        ->get('/admin/pos/sales-history?invoice=' . $saleNumber)
        ->assertInertia(fn ($page) => $page->has('sales.data', 1));

    $this->actingAs($admin)
        ->get('/admin/pos/sales-history?invoice=NOPE')
        ->assertInertia(fn ($page) => $page->has('sales.data', 0));
});

it('exports sales history as csv', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(40);

    $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 40]],
    ])->assertCreated();

    $response = $this->actingAs($admin)->get('/admin/pos/sales-history/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('downloads an A4 invoice PDF for a sale', function () use ($makeConfirmedUser, $openRegister, $makeService) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openRegister($admin);
    $service = $makeService(40);

    $saleId = $this->actingAs($admin)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 40]],
    ])->json('sale.id');

    $response = $this->actingAs($admin)->get("/admin/pos/sales/{$saleId}/invoice-pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
