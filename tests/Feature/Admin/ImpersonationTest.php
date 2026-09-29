<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Phase 5 sub-step 4 — required by the user to be written in THIS sub-step, not deferred: covers
 * exactly the three properties explicitly called out — a non-super-admin cannot start impersonation,
 * an impersonated session cannot start a nested one, and the exit route actually ends the session
 * (not just hides the banner).
 */
$makeConfirmedAdmin = function (string $role): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

it('lets a super-admin start impersonating another user', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin('super-admin');
    $target = $makeConfirmedAdmin('staff');

    $response = $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/impersonate");

    $response->assertRedirect(route('admin.dashboard'));
    expect(auth()->id())->toBe($target->id);
    expect(session('impersonator_id'))->toBe($superAdmin->id);
});

it('refuses to let a non-super-admin start impersonation', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedAdmin('admin'); // has users.manage-adjacent perms, but NOT the super-admin role
    $target = $makeConfirmedAdmin('staff');

    $response = $this->actingAs($admin)->post("/admin/users/{$target->id}/impersonate");

    $response->assertForbidden();
    expect(auth()->id())->toBe($admin->id); // still themself — impersonation never started
    expect(session('impersonator_id'))->toBeNull();
});

it('refuses to let an impersonated session start a NESTED impersonation', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin('super-admin');
    $firstTarget = $makeConfirmedAdmin('admin');
    // Give the first target super-admin too, so the ONLY thing that could block a second
    // impersonation attempt is the "already impersonating" check, not a role check.
    $firstTarget->assignRole('super-admin');
    $secondTarget = $makeConfirmedAdmin('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$firstTarget->id}/impersonate");
    expect(session('impersonator_id'))->toBe($superAdmin->id);

    $response = $this->post("/admin/users/{$secondTarget->id}/impersonate");

    $response->assertForbidden();
    // Still impersonating the FIRST target, not the second — the nested attempt changed nothing.
    expect(auth()->id())->toBe($firstTarget->id);
    expect(session('impersonator_id'))->toBe($superAdmin->id);
});

it('actually ends the impersonated session on exit, not just visually', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin('super-admin');
    $target = $makeConfirmedAdmin('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/impersonate");
    expect(auth()->id())->toBe($target->id);

    $response = $this->post('/admin/impersonate/stop');

    $response->assertRedirect(route('admin.users'));
    // The real, server-side authenticated identity is back to the original admin — not merely a
    // banner hidden client-side.
    expect(auth()->id())->toBe($superAdmin->id);
    expect(session('impersonator_id'))->toBeNull();

    // Exiting with nothing to exit (no impersonation in progress) is refused, not a silent no-op.
    $secondAttempt = $this->post('/admin/impersonate/stop');
    $secondAttempt->assertForbidden();
});

it('audit-logs both the start and end of an impersonation, with the real admin as actor', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin('super-admin');
    $target = $makeConfirmedAdmin('staff');

    $this->actingAs($superAdmin)->post("/admin/users/{$target->id}/impersonate");
    $this->post('/admin/impersonate/stop');

    $started = Activity::where('description', 'impersonation started')->first();
    $ended = Activity::where('description', 'impersonation ended')->first();

    expect($started)->not->toBeNull()
        ->and($started->causer_id)->toBe($superAdmin->id)
        ->and($started->subject_id)->toBe($target->id)
        ->and($ended)->not->toBeNull()
        ->and($ended->causer_id)->toBe($superAdmin->id) // the real admin, not the impersonated target
        ->and($ended->subject_id)->toBe($target->id);
});

it('refuses to let a super-admin impersonate themselves', function () use ($makeConfirmedAdmin) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedAdmin('super-admin');

    $response = $this->actingAs($superAdmin)->post("/admin/users/{$superAdmin->id}/impersonate");

    $response->assertSessionHasErrors('user');
    expect(session('impersonator_id'))->toBeNull();
});
