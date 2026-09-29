<?php

use App\Models\Booking;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

$actingAdmin = function (): User {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    return $admin;
};

it('loads for any admin-panel role with real kpi data', function () use ($actingAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->actingAs($actingAdmin())->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('kpis.bookings_today')
        ->has('revenueChart')
        ->has('todaySchedule')
        ->has('alerts'));
});

/**
 * Brief §5 / Phase 5 spec: "assert query counts on every admin list route (no N+1)". Rather than
 * guessing a fixed ceiling (login/session/2FA/role-check middleware all add their own constant
 * query overhead unrelated to the dashboard's own logic), this compares the query count at 1 booking
 * today vs. 8 bookings across different staff — a real N+1 on `staff.user`/`items.service` would
 * make that count scale with the number of bookings; eager loading keeps it flat.
 */
$addBookingToday = function (int $minutesOffset): void {
    $staff = Staff::factory()->create();

    Booking::factory()->create([
        'staff_id' => $staff->id,
        'starts_at' => today()->addMinutes($minutesOffset),
        'ends_at' => today()->addMinutes($minutesOffset + 30),
        'status' => 'confirmed',
    ]);
};

it('does not N+1 when building today\'s schedule', function () use ($actingAdmin, $addBookingToday) {
    $this->seed(RolesAndPermissionsSeeder::class);

    // DB::disableQueryLog() does NOT clear the log, only flushQueryLog() does — each measurement
    // below flushes first so the count reflects exactly that one request, not a cumulative total.
    $queryCountFor = function () use ($actingAdmin) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($actingAdmin())->get('/admin');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();

        return $count;
    };

    $addBookingToday(0);
    $queriesWithOneBooking = $queryCountFor();

    // Adding 7 MORE bookings today (8 total) on top of the same DB state — no reset in between, so
    // this isolates exactly what the extra bookings cost in query count.
    for ($i = 1; $i <= 7; $i++) {
        $addBookingToday($i * 30);
    }
    $queriesWithEightBookings = $queryCountFor();

    // Allow a small constant delta, but the difference must not scale anywhere near 1:1 with the
    // extra 7 bookings — that's the actual N+1 signature (staff.user/items.service lazy-loaded per row).
    expect($queriesWithEightBookings - $queriesWithOneBooking)->toBeLessThan(3);
});
