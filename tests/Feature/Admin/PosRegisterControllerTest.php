<?php

use App\Models\CashMovement;
use App\Models\CashRegisterShift;
use App\Models\Payment;
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

it('requires pos.sell or pos.refund to view the register', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // neither permission

    $this->actingAs($staff)->get('/admin/pos')->assertForbidden();
});

it('opens a register with a starting cash float', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');

    $this->actingAs($receptionist)->post('/admin/pos/register/open', ['opening_float' => 100])
        ->assertRedirect();

    $shift = CashRegisterShift::open()->first();
    expect($shift)->not->toBeNull()
        ->and((float) $shift->opening_float)->toBe(100.0)
        ->and($shift->staff_id)->toBe($receptionist->id);
});

it('refuses to open a second register while one is already open', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    CashRegisterShift::create(['staff_id' => $receptionist->id, 'opening_float' => 50, 'status' => 'open', 'opened_at' => now()]);

    $this->actingAs($receptionist)->post('/admin/pos/register/open', ['opening_float' => 100])
        ->assertStatus(409);

    expect(CashRegisterShift::where('status', 'open')->count())->toBe(1);
});

it('tracks cash deposits and withdrawals against the float', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $shift = CashRegisterShift::create(['staff_id' => $receptionist->id, 'opening_float' => 100, 'status' => 'open', 'opened_at' => now()]);

    $this->actingAs($receptionist)->post("/admin/pos/register/{$shift->id}/cash-in", [
        'amount' => 50,
        'reason' => 'Change fund top-up',
    ])->assertRedirect();

    $this->actingAs($receptionist)->post("/admin/pos/register/{$shift->id}/cash-out", [
        'amount' => 20,
        'reason' => 'Bank drop',
    ])->assertRedirect();

    expect(CashMovement::where('cash_register_shift_id', $shift->id)->count())->toBe(2);

    // Expected cash = 100 opening + 50 deposit - 20 withdrawal + 0 cash payments = 130.
    $response = $this->actingAs($receptionist)->get('/admin/pos');
    $response->assertInertia(fn ($page) => $page->where('shift.current_expected_cash', '130.00'));
});

it('refuses to remove more cash than is currently in the drawer', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist');
    $shift = CashRegisterShift::create(['staff_id' => $receptionist->id, 'opening_float' => 50, 'status' => 'open', 'opened_at' => now()]);

    $this->actingAs($receptionist)->post("/admin/pos/register/{$shift->id}/cash-out", ['amount' => 100])
        ->assertStatus(422);

    expect(CashMovement::count())->toBe(0);
});

it('calculates exact expected cash including succeeded cash payments and closes with the correct variance', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $shift = CashRegisterShift::create(['staff_id' => $admin->id, 'opening_float' => 100, 'status' => 'open', 'opened_at' => now()->subHour()]);

    $shift->cashMovements()->create(['staff_id' => $admin->id, 'type' => 'deposit', 'amount' => 20, 'reason' => 'top-up']);
    $shift->cashMovements()->create(['staff_id' => $admin->id, 'type' => 'withdrawal', 'amount' => 10, 'reason' => 'drop']);

    // Real cash payment recorded during the shift window (created_at defaults to now(), which falls
    // inside [opened_at, now()] since opened_at is an hour ago).
    Payment::create(['method' => 'cash', 'amount' => 45, 'status' => 'succeeded']);
    // Card payment must not count toward cash reconciliation.
    Payment::create(['method' => 'card', 'amount' => 200, 'status' => 'succeeded']);
    // Pending cash payment must be excluded.
    Payment::create(['method' => 'cash', 'amount' => 999, 'status' => 'pending']);

    // expected = 100 + 20 - 10 + 45 = 155
    $countedTotal = 150; // deliberate $5 shortage

    $response = $this->actingAs($admin)->post("/admin/pos/register/{$shift->id}/close", [
        'counted_total' => $countedTotal,
    ]);

    $response->assertRedirect();
    $shift->refresh();
    expect((float) $shift->expected_total)->toBe(155.0)
        ->and((float) $shift->counted_total)->toBe(150.0)
        ->and((float) $shift->variance)->toBe(-5.0)
        ->and($shift->status)->toBe('closed')
        ->and($shift->closed_at)->not->toBeNull();
});

it('refuses to close an already-closed register', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $shift = CashRegisterShift::create([
        'staff_id' => $admin->id,
        'opening_float' => 50,
        'status' => 'closed',
        'opened_at' => now()->subHours(2),
        'closed_at' => now()->subHour(),
        'expected_total' => 50,
        'counted_total' => 50,
        'variance' => 0,
    ]);

    $this->actingAs($admin)->post("/admin/pos/register/{$shift->id}/close", ['counted_total' => 50])
        ->assertStatus(409);
});

it('returns a Z-report only for a closed shift', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $openShift = CashRegisterShift::create(['staff_id' => $admin->id, 'opening_float' => 50, 'status' => 'open', 'opened_at' => now()]);

    $this->actingAs($admin)->getJson("/admin/pos/register/{$openShift->id}/z-report")->assertStatus(409);

    $openShift->update(['status' => 'closed', 'closed_at' => now(), 'expected_total' => 50, 'counted_total' => 50, 'variance' => 0]);

    $this->actingAs($admin)->getJson("/admin/pos/register/{$openShift->id}/z-report")
        ->assertOk()
        ->assertJsonPath('shift.status', 'closed');
});
