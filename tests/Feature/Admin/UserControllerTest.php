<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 5 sub-step 2: the first real (non-placeholder) admin controller — proves both DataTable's
 * server contract and that ->authorize() against the Phase 4 Policy layer actually works end to end.
 */
it('requires the users.manage permission to view the users list', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin'); // has catalog/pos/etc. but NOT users.manage per the seeder

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertForbidden();
});

it('lets a super-admin view the paginated users list', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole('super-admin');

    User::factory()->count(3)->create();

    $response = $this->actingAs($superAdmin)->get('/admin/users');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users')
        ->has('users.data')
        ->where('users.total', 4), // the 3 factory users + the super-admin themselves
    );
});

it('filters the users list by search term', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole('super-admin');

    User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@example.com']);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'someone-else@example.com']);

    $response = $this->actingAs($superAdmin)->get('/admin/users?search=Findable');

    $response->assertInertia(fn ($page) => $page
        ->where('users.total', 1)
        ->where('users.data.0.name', 'Findable Person'),
    );
});
