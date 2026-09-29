<?php

use App\Models\CashRegisterShift;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 14 sub-step 1 — Path B. Same intent as the customer journey test: every step hits a real
 * route Phase 12 already built and unit-tested (register lifecycle sub-step 2, checkout sub-step 3),
 * proving they genuinely chain together across one real shift, not just individually.
 *
 * "Apply Loyalty Discount" in the sub-step's own spec maps to the real feature Phase 12 actually
 * shipped — `loyalty_points` as one leg of a split payment (there's no separate "discount" concept
 * distinct from that) — used here as one half of the required multi-payment step too, so both parts
 * of the spec are covered by the same real checkout call rather than two separate, narrower ones.
 */
it('completes a full admin POS shift: open register, ring a multi-payment sale using loyalty points, close, and view the Z-report', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $receptionist->assignRole('receptionist');

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'name' => 'Signature Blowout',
        'base_price' => 60,
        'is_active' => true,
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    CustomerProfile::factory()->create(['user_id' => $customer->id, 'loyalty_points' => 5000]); // $50 worth at 100/$1

    // Step 1 — Open Register.
    $this->actingAs($receptionist)
        ->post('/admin/pos/register/open', ['opening_float' => 100])
        ->assertRedirect();

    $shift = CashRegisterShift::open()->first();
    expect($shift)->not->toBeNull()
        ->and((float) $shift->opening_float)->toBe(100.0);

    // Steps 2-4 — Add service to cart, apply the loyalty-points "discount", process a real
    // multi-payment (loyalty points + cash) in one checkout.
    $checkout = $this->actingAs($receptionist)->postJson('/admin/pos/checkout', [
        'items' => [['service_id' => $service->id, 'quantity' => 1]],
        'customer_id' => $customer->id,
        'payments' => [
            ['method' => 'loyalty_points', 'amount' => 20],
            ['method' => 'cash', 'amount' => 40],
        ],
    ]);
    $checkout->assertCreated();

    $sale = Sale::findOrFail($checkout->json('sale.id'));
    expect((float) $sale->total)->toBe(60.0)
        ->and($sale->status)->toBe('completed')
        ->and($sale->customer_id)->toBe($customer->id);
    expect(Payment::where('sale_id', $sale->id)->count())->toBe(2);
    expect(CustomerProfile::where('user_id', $customer->id)->value('loyalty_points'))->toBe(3000);

    // Step 5 — Close Shift. Only the real cash payment counts toward the drawer — the loyalty
    // portion was never physical cash — so expected = 100 opening float + 40 cash payment = 140.
    $this->actingAs($receptionist)
        ->post("/admin/pos/register/{$shift->id}/close", ['counted_total' => 140])
        ->assertRedirect();

    $shift->refresh();
    expect($shift->status)->toBe('closed')
        ->and((float) $shift->expected_total)->toBe(140.0)
        ->and((float) $shift->counted_total)->toBe(140.0)
        ->and((float) $shift->variance)->toBe(0.0);

    // Step 6 — Z-Report.
    $zReport = $this->actingAs($receptionist)->getJson("/admin/pos/register/{$shift->id}/z-report");
    $zReport->assertOk();
    expect($zReport->json('shift.status'))->toBe('closed')
        ->and($zReport->json('paymentBreakdown.cash'))->toBe('40.00')
        ->and($zReport->json('paymentBreakdown.loyalty_points'))->toBe('20.00')
        ->and($zReport->json('movements'))->toBeArray();
});
