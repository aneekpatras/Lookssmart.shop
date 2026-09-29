<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
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

it('requires reports.view to see the reports dashboard', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no reports.view

    $this->actingAs($staff)->get('/admin/reports')->assertForbidden();
});

it('calculates exact gross revenue, net revenue, discounts, and tax from completed bookings', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffMember = Staff::factory()->create(['commission_rate' => 10]);

    // total = (subtotal - discount) + tax. Booking A: subtotal 100, discount 10, tax 9 -> total 99.
    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'completed',
        'starts_at' => now()->startOfMonth()->addDays(2),
        'discount' => 10,
        'tax' => 9,
        'total' => 99,
    ]);
    // Booking B: subtotal 50, discount 0, tax 5 -> total 55.
    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'completed',
        'starts_at' => now()->startOfMonth()->addDays(3),
        'discount' => 0,
        'tax' => 5,
        'total' => 55,
    ]);
    // A non-completed booking in range must be excluded entirely.
    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'cancelled',
        'starts_at' => now()->startOfMonth()->addDays(4),
        'discount' => 0,
        'tax' => 0,
        'total' => 1000,
    ]);

    $response = $this->actingAs($admin)->get('/admin/reports?range=this_month');

    // net = SUM(total) - SUM(tax) = (99+55) - (9+5) = 154 - 14 = 140
    // gross = net + SUM(discount) = 140 + 10 = 150
    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Reports/Index')
        ->where('summary.gross_revenue', '150.00')
        ->where('summary.net_revenue', '140.00')
        ->where('summary.discounts', '10.00')
        ->where('summary.tax', '14.00')
        ->where('summary.total_transactions', 2)
        ->where('summary.average_order_value', '77.00'));
});

it('scopes results to the selected date range and excludes bookings outside it', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffMember = Staff::factory()->create();

    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'completed',
        'starts_at' => now(),
        'total' => 40,
        'discount' => 0,
        'tax' => 0,
    ]);
    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'completed',
        'starts_at' => now()->subDays(10),
        'total' => 999,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->actingAs($admin)->get('/admin/reports?range=today');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary.total_transactions', 1)
        ->where('summary.net_revenue', '40.00'));
});

it('computes staff performance revenue and commission from completed bookings', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffUser = User::factory()->create(['name' => 'Jamie Rivera']);
    $staffMember = Staff::factory()->create(['user_id' => $staffUser->id, 'commission_rate' => 20]);

    Booking::factory()->create([
        'staff_id' => $staffMember->id,
        'status' => 'completed',
        'starts_at' => now(),
        'total' => 100,
        'discount' => 0,
        'tax' => 0,
    ]);

    $response = $this->actingAs($admin)->get('/admin/reports?range=today');

    $response->assertInertia(fn ($page) => $page
        ->where('staffPerformance.0.name', 'Jamie Rivera')
        ->where('staffPerformance.0.revenue', '100.00')
        ->where('staffPerformance.0.commission', '20.00'));
});

it('ranks top services by real booking-item revenue', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create();
    $popular = Service::factory()->create(['service_category_id' => $category->id, 'name' => 'Signature Cut']);
    $staffMember = Staff::factory()->create();
    $booking = Booking::factory()->create(['staff_id' => $staffMember->id, 'status' => 'completed', 'starts_at' => now()]);
    BookingItem::create(['booking_id' => $booking->id, 'service_id' => $popular->id, 'price_snapshot' => 80, 'duration_snapshot' => 45]);

    $response = $this->actingAs($admin)->get('/admin/reports?range=today');

    $response->assertInertia(fn ($page) => $page
        ->where('topServices.0.name', 'Signature Cut')
        ->where('topServices.0.revenue', '80.00'));
});

it('reports a real payment method split from succeeded payments', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffMember = Staff::factory()->create();
    $booking = Booking::factory()->create(['staff_id' => $staffMember->id, 'status' => 'completed', 'starts_at' => now(), 'total' => 60]);
    Payment::create(['booking_id' => $booking->id, 'method' => 'card', 'amount' => 60, 'status' => 'succeeded']);
    Payment::create(['booking_id' => $booking->id, 'method' => 'cash', 'amount' => 10, 'status' => 'pending']); // must be excluded

    $response = $this->actingAs($admin)->get('/admin/reports?range=today');

    $response->assertInertia(fn ($page) => $page
        ->where('paymentMethodSplit', fn ($split) => count($split) === 1 && $split[0]['method'] === 'card' && $split[0]['total'] === '60.00'));
});

it('streams a CSV export with the correct content type', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staffMember = Staff::factory()->create();
    Booking::factory()->create(['staff_id' => $staffMember->id, 'status' => 'completed', 'starts_at' => now(), 'total' => 50, 'discount' => 0, 'tax' => 0]);

    $response = $this->actingAs($admin)->get('/admin/reports/export/csv?range=today');

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Gross Revenue')
        ->and($content)->toContain('50.00');
});

it('downloads a PDF export', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');

    $response = $this->actingAs($admin)->get('/admin/reports/export/pdf?range=today');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});
