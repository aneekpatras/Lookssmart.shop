<?php

use App\Models\User;
use App\Notifications\AdminInvitation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

$makeConfirmedAdmin = function (string $role = 'super-admin'): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

it('sends a signed invitation notification and never creates a user up front', function () use ($makeConfirmedAdmin) {
    NotificationFacade::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();

    $response = $this->actingAs($superAdmin)->post('/admin/users/invite', [
        'email' => 'invitee@example.com',
        'role' => 'staff',
    ]);

    $response->assertRedirect();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeFalse();
    NotificationFacade::assertSentOnDemand(AdminInvitation::class);
});

it('rejects an invite role outside the admin-panel role set', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();

    $response = $this->actingAs($superAdmin)->post('/admin/users/invite', [
        'email' => 'invitee@example.com',
        'role' => 'customer',
    ]);

    $response->assertSessionHasErrors('role');
});

it('completes a signed invite into a real, correctly-roled account', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $signedUrl = URL::temporarySignedRoute('invite.accept.show', now()->addDays(7), [
        'email' => 'newstaff@example.com',
        'role' => 'staff',
    ]);

    $response = $this->post($signedUrl, [
        'name' => 'New Staff Member',
        'password' => 'a-very-strong-password-123',
        'password_confirmation' => 'a-very-strong-password-123',
    ]);

    $user = User::where('email', 'newstaff@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('staff'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
    $response->assertRedirect(route('admin.dashboard'));
});

it('rejects an invite link with a tampered role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $signedUrl = URL::temporarySignedRoute('invite.accept.show', now()->addDays(7), [
        'email' => 'newstaff@example.com',
        'role' => 'staff',
    ]);

    $tamperedUrl = str_replace('role=staff', 'role=super-admin', $signedUrl);

    $response = $this->post($tamperedUrl, [
        'name' => 'Attacker',
        'password' => 'a-very-strong-password-123',
        'password_confirmation' => 'a-very-strong-password-123',
    ]);

    $response->assertForbidden();
    expect(User::where('email', 'newstaff@example.com')->exists())->toBeFalse();
});

it('updates a user\'s role via syncRoles', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();
    $target = $makeConfirmedAdmin('staff');

    $this->actingAs($superAdmin)->put("/admin/users/{$target->id}/role", ['role' => 'receptionist']);

    expect($target->fresh()->hasRole('receptionist'))->toBeTrue()
        ->and($target->fresh()->hasRole('staff'))->toBeFalse();
});

it('forces 2FA re-setup by nulling the confirmed-at timestamp', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();
    $target = $makeConfirmedAdmin('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/force-2fa");

    $fresh = $target->fresh();
    expect($fresh->two_factor_confirmed_at)->toBeNull()
        ->and($fresh->two_factor_secret)->toBeNull();
});

it('suspends a user, and a suspended user cannot log in', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();
    $target = User::factory()->create(['password' => 'password', 'email_verified_at' => now()]);
    $target->assignRole('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/suspend");
    expect($target->fresh()->isSuspended())->toBeTrue();

    // actingAs() sets the guard's user directly in the container for the rest of THIS test — forget
    // it so the next request re-resolves auth from its own (empty) session, like a real fresh guest
    // request would, rather than leaking the super-admin's identity into the login attempt below.
    $this->app['auth']->forgetGuards();

    $loginAttempt = $this->post('/login', [
        'email' => $target->email,
        'password' => 'password',
    ]);

    $loginAttempt->assertSessionHasErrors();
    $this->assertGuest();
});

it('prevents an admin from suspending their own account', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();

    $response = $this->actingAs($superAdmin)->post("/admin/users/{$superAdmin->id}/suspend");

    $response->assertSessionHasErrors('user');
    expect($superAdmin->fresh()->isSuspended())->toBeFalse();
});

it('unsuspends a user, restoring their ability to log in', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin();
    $target = User::factory()->create([
        'password' => 'password',
        'email_verified_at' => now(),
        'suspended_at' => now(),
    ]);
    $target->assignRole('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/unsuspend");
    expect($target->fresh()->isSuspended())->toBeFalse();

    $this->app['auth']->forgetGuards();

    $loginAttempt = $this->post('/login', ['email' => $target->email, 'password' => 'password']);
    $loginAttempt->assertRedirect();
    $this->assertAuthenticatedAs($target->fresh());
});
