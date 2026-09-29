<?php

use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
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

it('blocks a receptionist with no staff.manage and no linked staff record from viewing availability', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $receptionist = $makeConfirmedUser('receptionist'); // no staff.manage per the seeder

    $this->actingAs($receptionist)->get('/admin/availability')->assertForbidden();
});

it('lets an admin (staff.manage) view every staff member\'s schedule', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staff = Staff::factory()->create();
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00']);

    $response = $this->actingAs($admin)->get('/admin/availability');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Availability')
        ->has('staff', 1));
});

it('requires staff.manage to save a working-hours row', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staffUser = $makeConfirmedUser('staff');
    $staff = Staff::factory()->create(['user_id' => $staffUser->id]);

    $this->actingAs($staffUser)->post('/admin/availability/working-hours', [
        'staff_id' => $staff->id,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ])->assertForbidden();

    expect(StaffWorkingHour::where('staff_id', $staff->id)->exists())->toBeFalse();
});

it('lets an admin upsert a working-hours row for any staff member', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $staff = Staff::factory()->create();

    $this->actingAs($admin)->post('/admin/availability/working-hours', [
        'staff_id' => $staff->id,
        'weekday' => 2,
        'start_time' => '10:00',
        'end_time' => '18:00',
    ])->assertRedirect();

    $hour = StaffWorkingHour::where('staff_id', $staff->id)->where('weekday', 2)->first();
    expect($hour)->not->toBeNull();
    expect($hour->start_time)->toBe('10:00');

    // Same staff+weekday again updates in place rather than duplicating.
    $this->actingAs($admin)->post('/admin/availability/working-hours', [
        'staff_id' => $staff->id,
        'weekday' => 2,
        'start_time' => '11:00',
        'end_time' => '19:00',
    ]);

    expect(StaffWorkingHour::where('staff_id', $staff->id)->where('weekday', 2)->count())->toBe(1);
    expect($hour->fresh()->start_time)->toBe('11:00');
});

it('lets a staff member add time off for themselves but not for a colleague', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staffUser = $makeConfirmedUser('staff');
    $own = Staff::factory()->create(['user_id' => $staffUser->id]);
    $colleague = Staff::factory()->create();

    $this->actingAs($staffUser)->post('/admin/availability/time-off', [
        'staff_id' => $own->id,
        'starts_at' => now()->addWeek()->toDateString(),
        'ends_at' => now()->addWeek()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(StaffTimeOff::where('staff_id', $own->id)->exists())->toBeTrue();

    $this->actingAs($staffUser)->post('/admin/availability/time-off', [
        'staff_id' => $colleague->id,
        'starts_at' => now()->addWeek()->toDateString(),
        'ends_at' => now()->addWeek()->addDay()->toDateString(),
    ])->assertForbidden();

    expect(StaffTimeOff::where('staff_id', $colleague->id)->exists())->toBeFalse();
});

it('lets a staff member delete their own time off but not a colleague\'s', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staffUser = $makeConfirmedUser('staff');
    $own = Staff::factory()->create(['user_id' => $staffUser->id]);
    $colleague = Staff::factory()->create();
    $ownTimeOff = StaffTimeOff::create(['staff_id' => $own->id, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addDay()]);
    $colleagueTimeOff = StaffTimeOff::create(['staff_id' => $colleague->id, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addDay()]);

    $this->actingAs($staffUser)->delete("/admin/availability/time-off/{$colleagueTimeOff->id}")->assertForbidden();
    $this->actingAs($staffUser)->delete("/admin/availability/time-off/{$ownTimeOff->id}")->assertRedirect();

    expect(StaffTimeOff::find($colleagueTimeOff->id))->not->toBeNull();
    expect(StaffTimeOff::find($ownTimeOff->id))->toBeNull();
});
